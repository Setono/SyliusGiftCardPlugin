<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use RuntimeException;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddGiftCardToOrderAction
{
    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var CartContextInterface */
    private $cartContext;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var TranslatorInterface */
    private $translator;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var GiftCardApplicatorInterface */
    private $giftCardApplicator;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        CartContextInterface $cartContext,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        FlashBagInterface $flashBag,
        GiftCardApplicatorInterface $giftCardApplicator
    ) {
        $this->viewHandler = $viewHandler;
        $this->cartContext = $cartContext;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
        $this->flashBag = $flashBag;
        $this->giftCardApplicator = $giftCardApplicator;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();

        if (null === $order) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid((string) $order->getId(), $request->request->get('_csrf_token'))) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid csrf token.');
        }

        $orderChannel = $order->getChannel();
        if (null === $orderChannel) {
            throw new RuntimeException('The order does not have a channel');
        }

        try {
            $this->giftCardApplicator->apply($order, $request->get('code', ''));
        } catch (GiftCardNotFoundException $e) {
            $message = $this->translator->trans('setono_sylius_gift_card.ui.gift_card_code_is_invalid');

            return $this->viewHandler->handle(View::create(['error' => $message], Response::HTTP_BAD_REQUEST));
        } catch (ChannelMismatchException $e) {
            $message = $this->translator->trans('setono_sylius_gift_card.ui.gift_card_channel_does_not_match_channel', ['%channel%' => $orderChannel->getName()]);

            return $this->viewHandler->handle(View::create(['error' => $message], Response::HTTP_BAD_REQUEST));
        }

        $this->flashBag->add('success', 'sylius.cart.save');

        return $this->viewHandler->handle(View::create(null, Response::HTTP_NO_CONTENT));
    }

    private function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }
}
