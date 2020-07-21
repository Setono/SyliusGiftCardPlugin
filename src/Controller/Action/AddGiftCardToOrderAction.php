<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class AddGiftCardToOrderAction
{
    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var FormFactoryInterface */
    private $formFactory;

    /** @var CartContextInterface */
    private $cartContext;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var GiftCardApplicatorInterface */
    private $giftCardApplicator;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory,
        CartContextInterface $cartContext,
        FlashBagInterface $flashBag,
        UrlGeneratorInterface $urlGenerator,
        GiftCardApplicatorInterface $giftCardApplicator
    ) {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
        $this->cartContext = $cartContext;
        $this->flashBag = $flashBag;
        $this->urlGenerator = $urlGenerator;
        $this->giftCardApplicator = $giftCardApplicator;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();

        if (null === $order) {
            throw new NotFoundHttpException();
        }

        $addGiftCardToOrderCommand = new AddGiftCardToOrderCommand();
        $form = $this->formFactory->create(AddGiftCardToOrderType::class, $addGiftCardToOrderCommand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $giftCard = $addGiftCardToOrderCommand->getGiftCard();
            Assert::notNull($giftCard);
            $this->giftCardApplicator->apply($order, $giftCard);

            $this->flashBag->add('success', 'setono_sylius_gift_card.gift_card_added');

            if ($request->isXmlHttpRequest()) {
                return $this->viewHandler->handle(View::create([], Response::HTTP_CREATED));
            }

            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
        }

        if ($request->isXmlHttpRequest()) {
            return $this->viewHandler->handle(View::create($form, Response::HTTP_BAD_REQUEST)->setData([
                'errors' => $form->getErrors(true, true),
            ]));
        }

        $view = View::create()
            ->setData([
                'form' => $form->createView(),
            ])
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/addGiftCardToOrder.html.twig')
        ;

        return $this->viewHandler->handle($view);
    }
}
