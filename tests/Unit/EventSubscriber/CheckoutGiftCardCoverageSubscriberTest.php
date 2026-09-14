<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\EventSubscriber\CheckoutGiftCardCoverageSubscriber;
use Setono\SyliusGiftCardPlugin\Guard\GiftCardCoverageGuardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CheckoutGiftCardCoverageSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CartContextInterface> */
    private ObjectProphecy $cartContext;

    /** @var ObjectProphecy<GiftCardCoverageGuardInterface> */
    private ObjectProphecy $guard;

    /** @var ObjectProphecy<GiftCardApplicatorInterface> */
    private ObjectProphecy $applicator;

    /** @var ObjectProphecy<OrderProcessorInterface> */
    private ObjectProphecy $orderProcessor;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->cartContext = $this->prophesize(CartContextInterface::class);
        $this->guard = $this->prophesize(GiftCardCoverageGuardInterface::class);
        $this->applicator = $this->prophesize(GiftCardApplicatorInterface::class);
        $this->orderProcessor = $this->prophesize(OrderProcessorInterface::class);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
    }

    /** @test */
    public function it_ignores_requests_that_are_not_the_checkout_complete_step(): void
    {
        $this->cartContext->getCart()->shouldNotBeCalled();

        $event = $this->requestEvent(new Request());
        $this->subscriber()($event);

        self::assertNull($event->getResponse());
    }

    /** @test */
    public function it_ignores_sub_requests(): void
    {
        $this->cartContext->getCart()->shouldNotBeCalled();

        $event = $this->requestEvent($this->completeStepRequest(), HttpKernelInterface::SUB_REQUEST);
        $this->subscriber()($event);

        self::assertNull($event->getResponse());
    }

    /** @test */
    public function it_leaves_a_cart_the_guard_is_satisfied_with_alone(): void
    {
        $cart = $this->cartWithGiftCards();
        $this->guard->isSatisfiedBy($cart)->willReturn(true);
        $this->applicator->remove(Argument::cetera())->shouldNotBeCalled();
        $this->orderProcessor->process(Argument::any())->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $event = $this->requestEvent($this->completeStepRequest());
        $this->subscriber()($event);

        self::assertNull($event->getResponse());
    }

    /** @test */
    public function it_removes_the_gift_cards_that_can_no_longer_be_used_and_sends_the_customer_back_to_the_cart(): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getCode()->willReturn('STALE00000000001');

        $cart = $this->cartWithGiftCards();
        $this->guard->isSatisfiedBy($cart)->willReturn(false);
        $this->guard->getInapplicableGiftCards($cart)->willReturn([$giftCard->reveal()]);

        $this->applicator->remove($cart, $giftCard->reveal())->shouldBeCalledOnce();
        $this->orderProcessor->process(Argument::any())->shouldNotBeCalled();
        $this->manager->flush()->shouldBeCalledOnce();

        $request = $this->completeStepRequest();
        $event = $this->requestEvent($request);
        $this->subscriber()($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/cart', $response->getTargetUrl());

        self::assertSame([[
            'message' => 'setono_sylius_gift_card.gift_card.no_longer_usable',
            'parameters' => ['%code%' => 'STALE00000000001'],
        ]], $this->flashes($request));
    }

    /** @test */
    public function it_resizes_the_gateway_payment_when_the_gift_cards_can_still_be_used_but_cover_less(): void
    {
        $cart = $this->cartWithGiftCards();
        $this->guard->isSatisfiedBy($cart)->willReturn(false);
        $this->guard->getInapplicableGiftCards($cart)->willReturn([]);

        $this->applicator->remove(Argument::cetera())->shouldNotBeCalled();
        $this->orderProcessor->process($cart)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $request = $this->completeStepRequest();
        $event = $this->requestEvent($request);
        $this->subscriber()($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame([[
            'message' => 'setono_sylius_gift_card.gift_card.coverage_changed',
            'parameters' => [],
        ]], $this->flashes($request));
    }

    private function subscriber(): CheckoutGiftCardCoverageSubscriber
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('sylius_shop_cart_summary')->willReturn('/cart');

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::type('string'))->willReturn($this->manager->reveal());

        return new CheckoutGiftCardCoverageSubscriber(
            $this->cartContext->reveal(),
            $this->guard->reveal(),
            $this->applicator->reveal(),
            $this->orderProcessor->reveal(),
            $urlGenerator->reveal(),
            $managerRegistry->reveal(),
        );
    }

    private function cartWithGiftCards(): OrderInterface
    {
        $cart = $this->prophesize(OrderInterface::class);
        $cart->hasGiftCards()->willReturn(true);
        $this->cartContext->getCart()->willReturn($cart->reveal());

        return $cart->reveal();
    }

    /**
     * What the resource routing attaches to the complete step of the checkout, whether looked at or submitted
     */
    private function completeStepRequest(): Request
    {
        $request = new Request();
        $request->attributes->set('_sylius', [
            'state_machine' => [
                'graph' => OrderCheckoutTransitions::GRAPH,
                'transition' => OrderCheckoutTransitions::TRANSITION_COMPLETE,
            ],
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function requestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, $requestType);
    }

    /**
     * @return list<mixed>
     */
    private function flashes(Request $request): array
    {
        $session = $request->getSession();
        self::assertInstanceOf(Session::class, $session);

        return array_values($session->getFlashBag()->peek('error'));
    }
}
