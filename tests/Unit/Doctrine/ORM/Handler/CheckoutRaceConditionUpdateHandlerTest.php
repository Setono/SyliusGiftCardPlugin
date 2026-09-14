<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Doctrine\ORM\Handler;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\Handler\CheckoutRaceConditionUpdateHandler;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardRaceConditionSubscriber;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Resource\Exception\RaceConditionException;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CheckoutRaceConditionUpdateHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_delegates_when_nothing_goes_wrong(): void
    {
        $decorated = $this->prophesize(ResourceUpdateHandlerInterface::class);
        $decorated->handle(Argument::cetera())->shouldBeCalledOnce();

        $this->handle($decorated, GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE);
    }

    /**
     * Sylius' resource controller catches the RaceConditionException and redirects to the referer without a
     * flash, so the underlying Doctrine exception is let through for GiftCardRaceConditionSubscriber instead
     *
     * @test
     */
    public function it_unwraps_a_lost_race_at_checkout_completion(): void
    {
        $optimisticLockException = OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2);

        $decorated = $this->prophesize(ResourceUpdateHandlerInterface::class);
        $decorated->handle(Argument::cetera())->willThrow(new RaceConditionException($optimisticLockException));

        try {
            $this->handle($decorated, GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE);

            self::fail('the exception should have been rethrown');
        } catch (\Throwable $thrown) {
            self::assertSame($optimisticLockException, $thrown);
        }
    }

    /** @test */
    public function it_leaves_other_routes_to_sylius(): void
    {
        $raceConditionException = new RaceConditionException(
            OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2),
        );

        $decorated = $this->prophesize(ResourceUpdateHandlerInterface::class);
        $decorated->handle(Argument::cetera())->willThrow($raceConditionException);

        try {
            $this->handle($decorated, 'sylius_admin_product_update');

            self::fail('the exception should have been rethrown');
        } catch (\Throwable $thrown) {
            self::assertSame($raceConditionException, $thrown);
        }
    }

    /** @test */
    public function it_leaves_a_race_condition_that_is_not_an_optimistic_lock_to_sylius(): void
    {
        $raceConditionException = new RaceConditionException();

        $decorated = $this->prophesize(ResourceUpdateHandlerInterface::class);
        $decorated->handle(Argument::cetera())->willThrow($raceConditionException);

        try {
            $this->handle($decorated, GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE);

            self::fail('the exception should have been rethrown');
        } catch (\Throwable $thrown) {
            self::assertSame($raceConditionException, $thrown);
        }
    }

    /**
     * @param \Prophecy\Prophecy\ObjectProphecy<ResourceUpdateHandlerInterface> $decorated
     */
    private function handle(\Prophecy\Prophecy\ObjectProphecy $decorated, string $route): void
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $handler = new CheckoutRaceConditionUpdateHandler($decorated->reveal(), $requestStack);

        $handler->handle(
            $this->prophesize(ResourceInterface::class)->reveal(),
            $this->prophesize(RequestConfiguration::class)->reveal(),
            $this->prophesize(ObjectManager::class)->reveal(),
        );
    }
}
