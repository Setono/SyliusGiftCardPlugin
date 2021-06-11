<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardToOrderType;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\Assert;

final class AddGiftCardToOrderAction
{
    private ViewHandlerInterface $viewHandler;

    private FormFactoryInterface $formFactory;

    private CartContextInterface $cartContext;

    private FlashBagInterface $flashBag;

    private GiftCardApplicatorInterface $giftCardApplicator;

    private RedirectUrlResolverInterface $redirectRouteResolver;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory,
        CartContextInterface $cartContext,
        FlashBagInterface $flashBag,
        GiftCardApplicatorInterface $giftCardApplicator,
        RedirectUrlResolverInterface $redirectRouteResolver
    ) {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
        $this->cartContext = $cartContext;
        $this->flashBag = $flashBag;
        $this->giftCardApplicator = $giftCardApplicator;
        $this->redirectRouteResolver = $redirectRouteResolver;
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

            return new RedirectResponse($this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'));
        }

        $view = View::create()
            ->setData([
                // Apparently we have to pass the form, and not the createdView
                'form' => $form,
            ])
            ->setTemplate('@SetonoSyliusGiftCardPlugin/Shop/addGiftCardToOrder.html.twig')
        ;

        return $this->viewHandler->handle($view);
    }
}
