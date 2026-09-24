<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Webmozart\Assert\Assert;

final class AddGiftCardToOrderAction
{
    use ORMTrait;

    /**
     * @param RateLimiterFactory|null $rateLimiterFactory   The per visitor (session) limiter; null when
     *                                                      setono_sylius_gift_card.redemption.rate_limiter is null
     * @param RateLimiterFactory|null $ipRateLimiterFactory The per client IP limiter; null when
     *                                                      setono_sylius_gift_card.redemption.ip_rate_limiter is null
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly CartContextInterface $cartContext,
        private readonly GiftCardApplicatorInterface $giftCardApplicator,
        private readonly RedirectUrlResolverInterface $redirectRouteResolver,
        ManagerRegistry $managerRegistry,
        private readonly ?RateLimiterFactory $rateLimiterFactory = null,
        private readonly ?RateLimiterFactory $ipRateLimiterFactory = null,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        // Throttled before anything else happens: this is the only place in the shop where a visitor can
        // probe whether a gift card code exists, so guessing has to cost the guesser time
        if (!$this->consumeRateLimiterToken($request)) {
            $this->addFlash($request, 'error', 'setono_sylius_gift_card.gift_card.too_many_attempts');

            return $this->redirect($request);
        }

        $order = $this->cartContext->getCart();
        if (!$order instanceof OrderInterface) {
            throw new NotFoundHttpException();
        }

        $command = new AddGiftCardToOrderCommand();
        $form = $this->formFactory->create(AddGiftCardToOrderType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $giftCard = $command->getGiftCard();
            Assert::notNull($giftCard);

            $this->giftCardApplicator->apply($order, $giftCard);
            $this->getManager($order)->flush();

            $this->addFlash($request, 'success', 'setono_sylius_gift_card.gift_card_added');
        } else {
            foreach ($this->collectErrors($form) as $error) {
                $this->addFlash($request, 'error', $error);
            }
        }

        return $this->redirect($request);
    }

    /**
     * Consumes a token per bucket this visitor falls into and tells whether the attempt is allowed
     */
    private function consumeRateLimiterToken(Request $request): bool
    {
        $accepted = true;

        foreach ($this->rateLimiters($request) as $limiter) {
            // every bucket is consumed even when an earlier one already refused, so that exhausting one
            // bucket cannot be used to spend from another
            $accepted = $limiter->consume()->isAccepted() && $accepted;
        }

        return $accepted;
    }

    /**
     * The attempt is counted against the visitor's session and against their IP separately, and both have to
     * accept: a guesser that throws its cookies away to get a fresh session still runs into the limit for its
     * IP, and one that comes in through a pool of addresses still runs into the limit for its session.
     *
     * Everyone behind one address shares its bucket (an office NAT, a mobile carrier's CGNAT, and every customer
     * of a shop whose proxy is not in framework.trusted_proxies), which is why the IP limiter the plugin
     * registers allows more attempts than the session one
     *
     * @return list<LimiterInterface>
     */
    private function rateLimiters(Request $request): array
    {
        $limiters = [];

        $sessionId = $this->sessionId($request);
        if (null !== $this->rateLimiterFactory && null !== $sessionId) {
            // the keys are prefixed so the two buckets stay apart if both options name the same limiter
            $limiters[] = $this->rateLimiterFactory->create(sprintf('session-%s', $sessionId));
        }

        // Only known addresses get a bucket; one shared by every request without an address would throttle
        // strangers together
        $clientIp = $request->getClientIp();
        if (null !== $this->ipRateLimiterFactory && null !== $clientIp) {
            $limiters[] = $this->ipRateLimiterFactory->create(sprintf('ip-%s', $clientIp));
        }

        return $limiters;
    }

    /**
     * The id is read from the cookie the client sent rather than from the session itself, because the session
     * is typically not started yet this early in the request, and a session started here would hand out a
     * brand new id, and with it a brand new budget, on every single attempt
     */
    private function sessionId(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $sessionId = $request->cookies->get($request->getSession()->getName());

        return is_string($sessionId) && '' !== $sessionId ? $sessionId : null;
    }

    private function redirect(Request $request): RedirectResponse
    {
        return new RedirectResponse(
            $this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'),
        );
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    /**
     * @param FormInterface<AddGiftCardToOrderCommand> $form
     *
     * @return list<string>
     */
    private function collectErrors(FormInterface $form): array
    {
        // A code that matches no gift card fails in the data transformer, which leaves the command empty and
        // therefore makes the NotBlank constraint fire on top of the transformation failure. Reporting both
        // would set an unknown code apart from a code that exists but cannot be used, which is precisely what
        // a code guesser is after, so only the generic message is reported
        foreach ($form as $child) {
            if ($child->isSubmitted() && !$child->isSynchronized()) {
                return ['setono_sylius_gift_card.gift_card.could_not_be_applied'];
            }
        }

        $errors = [];

        /** @var FormError $error */
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        if ([] === $errors) {
            $errors[] = 'setono_sylius_gift_card.gift_card.could_not_be_applied';
        }

        return $errors;
    }
}
