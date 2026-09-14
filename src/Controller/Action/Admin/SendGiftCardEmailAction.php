<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

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
 * Emails a gift card to its customer on demand, so an admin can send a card that was created disabled (and
 * therefore not emailed) or resend one the customer lost.
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

        // The email manager silently does nothing without an address, which would leave the admin thinking the
        // card was sent, so the missing recipient is reported instead
        $hasRecipient = null !== $giftCard->getCustomer()?->getEmail();
        if ($hasRecipient) {
            $this->emailManager->sendGiftCard($giftCard);
        }

        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add(
                $hasRecipient ? 'success' : 'error',
                $hasRecipient
                    ? 'setono_sylius_gift_card.gift_card.email_sent'
                    : 'setono_sylius_gift_card.gift_card.email_not_sent_no_customer',
            );
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_show', [
            'id' => $id,
        ]));
    }
}
