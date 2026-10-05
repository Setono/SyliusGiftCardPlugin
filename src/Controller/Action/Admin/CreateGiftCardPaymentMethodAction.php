<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action\Admin;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardPaymentMethodFactoryInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Creates the payment method gift card payments are made with, the way setono:gift-card:create-payment-method does, so
 * a merchant can complete the setup from the warning the admin shows while it is missing.
 *
 * It creates something, so the route only takes a POST carrying a CSRF token: a plain link could be hit by a browser
 * prefetch or an <img src> on any page the admin visits. When the method already exists, because another tab, another
 * admin or a deploy created it since the warning was rendered, nothing is created
 */
final class CreateGiftCardPaymentMethodAction
{
    use ORMTrait;

    /**
     * The id of the CSRF token the request must carry; the warning rendering the button uses the same id
     */
    public const CSRF_TOKEN_ID = 'setono_create_gift_card_payment_method';

    public function __construct(
        private readonly GiftCardPaymentMethodProviderInterface $paymentMethodProvider,
        private readonly GiftCardPaymentMethodFactoryInterface $paymentMethodFactory,
        ManagerRegistry $managerRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $created = null === $this->paymentMethodProvider->findPaymentMethod() && $this->createPaymentMethod();

        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add(
                $created ? 'success' : 'info',
                $created
                    ? 'setono_sylius_gift_card.gift_card.payment_method_created'
                    : 'setono_sylius_gift_card.gift_card.payment_method_already_exists',
            );
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_index'));
    }

    /**
     * @return bool false when a request that got here at the same time created it first, a double click on the button
     *              say: the code is unique, so the second flush fails rather than creating another method
     */
    private function createPaymentMethod(): bool
    {
        $paymentMethod = $this->paymentMethodFactory->create();

        $manager = $this->getManager($paymentMethod);
        $manager->persist($paymentMethod);

        try {
            $manager->flush();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
