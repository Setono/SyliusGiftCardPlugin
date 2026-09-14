<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Webmozart\Assert\Assert;

final class RemoveGiftCardFromOrderAction
{
    use ORMTrait;

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly GiftCardApplicatorInterface $giftCardApplicator,
        private readonly RedirectUrlResolverInterface $redirectRouteResolver,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly GiftCardCodeNormalizerInterface $codeNormalizer,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function __invoke(Request $request, string $giftCard): Response
    {
        $order = $this->cartContext->getCart();
        Assert::isInstanceOf($order, OrderInterface::class);

        // The cart template derives the token from the stored code, so the route parameter is brought to the
        // same canonical form before the two are compared, and the applicator is handed that same form
        $code = $this->codeNormalizer->normalize($giftCard);

        $token = (string) $request->request->get('_csrf_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('setono_remove_gift_card_' . $code, $token))) {
            throw new NotFoundHttpException();
        }

        $this->giftCardApplicator->remove($order, $code);
        $this->getManager($order)->flush();

        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('success', 'setono_sylius_gift_card.gift_card_removed');
        }

        return new RedirectResponse(
            $this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'),
        );
    }
}
