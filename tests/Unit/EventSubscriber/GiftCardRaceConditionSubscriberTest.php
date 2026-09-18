<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardRaceConditionSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GiftCardRaceConditionSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Symfony's error listener logs at priority 0 and renders the error page at -128
     *
     * @test
     */
    public function it_runs_between_logging_and_rendering_the_error_page(): void
    {
        self::assertSame(
            [KernelEvents::EXCEPTION => ['onKernelException', -64]],
            GiftCardRaceConditionSubscriber::getSubscribedEvents(),
        );
    }

    /** @test */
    public function it_sends_the_customer_back_to_the_cart_with_an_explanation(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $event = $this->exceptionEvent(
            GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE,
            OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2),
            $session,
        );

        $this->subscriber()->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/cart', $response->getTargetUrl());
        self::assertSame(
            [GiftCardRaceConditionSubscriber::FLASH],
            $session->getFlashBag()->get('error'),
        );

        // Symfony's error listener would otherwise replace the redirect with an error page
        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * Sylius does not rethrow the Doctrine exception as-is everywhere, so a wrapped one has to be recognised too
     *
     * @test
     */
    public function it_looks_through_the_exception_chain(): void
    {
        $event = $this->exceptionEvent(
            GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE,
            new \RuntimeException('Operated entity was previously modified.', 0, OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2)),
        );

        $this->subscriber()->onKernelException($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    /** @test */
    public function it_ignores_other_routes(): void
    {
        $event = $this->exceptionEvent(
            'sylius_shop_checkout_select_payment',
            OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2),
        );

        $this->subscriber()->onKernelException($event);

        self::assertNull($event->getResponse());
        self::assertFalse($event->isPropagationStopped());
    }

    /** @test */
    public function it_ignores_other_exceptions(): void
    {
        $event = $this->exceptionEvent(
            GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE,
            new \RuntimeException('something else went wrong'),
        );

        $this->subscriber()->onKernelException($event);

        self::assertNull($event->getResponse());
        self::assertFalse($event->isPropagationStopped());
    }

    /** @test */
    public function it_redirects_even_without_a_session(): void
    {
        $event = $this->exceptionEvent(
            GiftCardRaceConditionSubscriber::CHECKOUT_COMPLETE_ROUTE,
            OptimisticLockException::lockFailedVersionMismatch(new \stdClass(), 1, 2),
        );

        $this->subscriber()->onKernelException($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
    }

    private function subscriber(): GiftCardRaceConditionSubscriber
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator
            ->generate(GiftCardRaceConditionSubscriber::CART_SUMMARY_ROUTE, Argument::cetera())
            ->willReturn('/cart')
        ;

        return new GiftCardRaceConditionSubscriber($urlGenerator->reveal());
    }

    private function exceptionEvent(string $route, \Throwable $throwable, ?Session $session = null): ExceptionEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        if (null !== $session) {
            $request->setSession($session);
        }

        return new ExceptionEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
