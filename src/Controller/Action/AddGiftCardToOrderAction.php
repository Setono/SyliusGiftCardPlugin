<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatorInterface;

final class AddGiftCardToOrderAction
{
    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var OrderProcessorInterface */
    private $orderProcessor;

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

    /** @var EntityManagerInterface */
    private $giftCardCodeEntityManager;

    public function __construct(
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        OrderProcessorInterface $orderProcessor,
        ViewHandlerInterface $viewHandler,
        CartContextInterface $cartContext,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        FlashBagInterface $flashBag,
        EntityManagerInterface $giftCardCodeEntityManager
    ) {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->orderProcessor = $orderProcessor;
        $this->viewHandler = $viewHandler;
        $this->cartContext = $cartContext;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
        $this->flashBag = $flashBag;
        $this->giftCardCodeEntityManager = $giftCardCodeEntityManager;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
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

        if (null === $order->getChannel()) {
            throw new NotFoundHttpException('The channel was not found on the order');
        }

        $giftCardCode = $this->giftCardCodeRepository->findOneActiveByCodeAndChannel(
            $request->get('code'),
            $order->getChannel()
        );

        if (null === $giftCardCode) {
            $message = $this->translator->trans('setono_sylius_gift_card.ui.gift_card_code_is_invalid');

            return $this->viewHandler->handle(View::create(['error' => $message], Response::HTTP_BAD_REQUEST));
        }

        $giftCardCode->setCurrentOrder($order);

        $this->giftCardCodeEntityManager->flush();

        $this->orderProcessor->process($order);

        $this->giftCardCodeEntityManager->flush();

        $this->flashBag->add('success', 'sylius.cart.save');

        return $this->viewHandler->handle(View::create(null, Response::HTTP_NO_CONTENT));
    }

    private function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }
}
