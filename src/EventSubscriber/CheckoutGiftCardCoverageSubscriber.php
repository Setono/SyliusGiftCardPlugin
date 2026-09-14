<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Guard\GiftCardCoverageGuardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Puts a cart right before the customer completes checkout with gift cards that no longer pay what they did
 *
 * Nothing re-validates an applied gift card between the cart and "Place order": it may have been spent from another
 * cart, disabled or adjusted in the meantime, while the payment step was skipped (or the gateway payment sized) on
 * the strength of it. The state machine guard refuses to complete such a checkout, which is the right hard stop but a
 * dead end for the customer: Sylius' CheckoutResolver asks that same guard on every request to a checkout step and,
 * told no, redirects to the step matching the cart's checkout state, which is the complete step again. So, before
 * the resolver gets to ask, this removes the cards that cannot be used any more (or, when they all still can but
 * cover less, re-sizes the gateway payment), tells the customer why and sends them back to the cart, from where
 * checkout starts over and the payment step is no longer skipped
 */
final class CheckoutGiftCardCoverageSubscriber implements EventSubscriberInterface
{
    use ORMTrait;

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly GiftCardCoverageGuardInterface $guard,
        private readonly GiftCardApplicatorInterface $giftCardApplicator,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly UrlGeneratorInterface $urlGenerator,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the firewall (8), which the cart context needs to know the customer, and before Sylius'
            // CheckoutResolver (0) asks the state machine whether the request's transition can be applied
            KernelEvents::REQUEST => ['__invoke', 4],
        ];
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!self::completesCheckout($request)) {
            return;
        }

        $cart = $this->getCart();
        if (null === $cart || !$cart->hasGiftCards() || $this->guard->isSatisfiedBy($cart)) {
            return;
        }

        $flashes = [];

        $removed = $this->guard->getInapplicableGiftCards($cart);
        foreach ($removed as $giftCard) {
            // detaches the card and re-processes the cart, so the gateway payment is sized to what is left to pay
            $this->giftCardApplicator->remove($cart, $giftCard);

            $flashes[] = [
                'message' => 'setono_sylius_gift_card.gift_card.no_longer_usable',
                'parameters' => ['%code%' => (string) $giftCard->getCode()],
            ];
        }

        if ([] === $removed) {
            // every card can still be used, so one of them covers less than it did: the same re-processing
            $this->orderProcessor->process($cart);

            $flashes[] = [
                'message' => 'setono_sylius_gift_card.gift_card.coverage_changed',
                'parameters' => [],
            ];
        }

        $this->getManager($cart)->flush();

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof Session) {
            foreach ($flashes as $flash) {
                $session->getFlashBag()->add('error', $flash);
            }
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary')));
    }

    /**
     * Whether the request drives the checkout state machine's complete transition, i.e. is the complete step of the
     * checkout, whether the customer is looking at it or placing the order
     */
    private static function completesCheckout(Request $request): bool
    {
        /** @var mixed $configuration */
        $configuration = $request->attributes->get('_sylius');
        if (!is_array($configuration)) {
            return false;
        }

        $stateMachine = $configuration['state_machine'] ?? null;
        if (!is_array($stateMachine)) {
            return false;
        }

        return OrderCheckoutTransitions::GRAPH === ($stateMachine['graph'] ?? null) &&
            OrderCheckoutTransitions::TRANSITION_COMPLETE === ($stateMachine['transition'] ?? null);
    }

    private function getCart(): ?OrderInterface
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return null;
        }

        return $cart instanceof OrderInterface ? $cart : null;
    }
}
