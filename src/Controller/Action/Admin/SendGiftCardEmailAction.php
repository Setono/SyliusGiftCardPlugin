<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Emails a gift card to its customer on demand, so an admin can send a card that was not emailed when it was created
 * (it could not be used yet, or nobody asked for the email) or resend one the customer lost. Like the email on
 * creation, it only sends a card the customer can use: any other would arrive as a gift that does not work.
 *
 * Sending is a side effect the customer sees, so the route only takes a POST carrying a CSRF token: a plain
 * link could be hit by a browser prefetch or an <img src> on any page the admin visits
 */
final class SendGiftCardEmailAction
{
    /**
     * The id of the CSRF token the request must carry; the grid action and the show page use the same id
     */
    public const CSRF_TOKEN_ID = 'setono_send_gift_card_email';

    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly GiftCardEmailManagerInterface $emailManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly GiftCardEligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $giftCard = $this->giftCardRepository->find($id);
        if (!$giftCard instanceof GiftCardInterface) {
            throw new NotFoundHttpException();
        }

        // The grid and the show page only offer the action for a usable card, so a card that cannot be used gets here
        // from a page opened before it stopped being usable, e.g. before its order was refunded
        if (!$giftCard->isUsable()) {
            return $this->redirectToGiftCard($request, $id, 'error', $this->notUsableMessage($giftCard));
        }

        // The email manager silently does nothing without an address, which would leave the admin thinking the
        // card was sent, so the missing recipient is reported instead
        if (null === $giftCard->getCustomer()?->getEmail()) {
            return $this->redirectToGiftCard($request, $id, 'error', 'setono_sylius_gift_card.gift_card.email_not_sent_no_customer');
        }

        $this->emailManager->sendGiftCard($giftCard);

        return $this->redirectToGiftCard($request, $id, 'success', 'setono_sylius_gift_card.gift_card.email_sent');
    }

    /**
     * Says why the card cannot be used, so the admin knows what to change before sending it
     */
    private function notUsableMessage(GiftCardInterface $giftCard): string
    {
        return match ($this->eligibilityChecker->getIneligibilityReason($giftCard)) {
            GiftCardIneligibilityReason::NotEnabled => 'setono_sylius_gift_card.gift_card.email_not_sent_disabled',
            GiftCardIneligibilityReason::Expired => 'setono_sylius_gift_card.gift_card.email_not_sent_expired',
            GiftCardIneligibilityReason::NoBalance => 'setono_sylius_gift_card.gift_card.email_not_sent_no_balance',
            // An application that makes more cards unusable than the checker names a reason for
            default => 'setono_sylius_gift_card.gift_card.email_not_sent_not_usable',
        };
    }

    private function redirectToGiftCard(Request $request, int $id, string $flashType, string $flashMessage): RedirectResponse
    {
        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add($flashType, $flashMessage);
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_show', [
            'id' => $id,
        ]));
    }
}
