<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber\Workflow;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\CommitRedemptionSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\DisableGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\EnableGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\ReconcileGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\RollbackRedemptionSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\SendGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Webmozart\Assert\Assert;

/**
 * These subscribers are the Symfony Workflow counterparts of the winzou callbacks prepended in
 * SetonoSyliusGiftCardExtension, so each has to forward the order to exactly the same collaborator method
 */
final class WorkflowSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider operatorSubscribers
     *
     * @param \Closure(OrderGiftCardOperatorInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_operator(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $call = $operator->__call($method, [$order]);
        Assert::isInstanceOf($call, MethodProphecy::class);
        $call->shouldBeCalledOnce();

        $subscriber = $factory($operator->reveal());
        $subscriber($this->completedEvent($order));
    }

    /**
     * @dataProvider redemptionSubscribers
     *
     * @param \Closure(GiftCardRedemptionMethodInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_redemption_method(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $call = $redemptionMethod->__call($method, [$order]);
        Assert::isInstanceOf($call, MethodProphecy::class);
        $call->shouldBeCalledOnce();

        $subscriber = $factory($redemptionMethod->reveal());
        $subscriber($this->completedEvent($order));
    }

    /** @test */
    public function it_rejects_a_subject_that_is_not_an_order(): void
    {
        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $operator->enable(\Prophecy\Argument::cetera())->shouldNotBeCalled();

        $subscriber = new EnableGiftCardsSubscriber($operator->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $subscriber($this->completedEvent(new \stdClass()));
    }

    /**
     * @return iterable<string, array{\Closure(OrderGiftCardOperatorInterface):callable, string}>
     */
    public function operatorSubscribers(): iterable
    {
        yield 'reconcile on checkout complete' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new ReconcileGiftCardsSubscriber($operator),
            'reconcile',
        ];
        yield 'enable on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new EnableGiftCardsSubscriber($operator),
            'enable',
        ];
        yield 'send on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new SendGiftCardsSubscriber($operator),
            'send',
        ];
        yield 'disable on order cancelled' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new DisableGiftCardsSubscriber($operator),
            'disable',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(GiftCardRedemptionMethodInterface):callable, string}>
     */
    public function redemptionSubscribers(): iterable
    {
        yield 'commit on order created' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new CommitRedemptionSubscriber($method),
            'commit',
        ];
        yield 'rollback on order cancelled' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new RollbackRedemptionSubscriber($method),
            'rollback',
        ];
    }

    private function completedEvent(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('t', 'from', 'to'));
    }
}
