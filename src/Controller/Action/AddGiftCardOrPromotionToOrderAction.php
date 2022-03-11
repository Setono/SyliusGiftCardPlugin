<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Applicator\GiftCardOrPromotionApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardOrPromotionToOrderType;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class AddGiftCardOrPromotionToOrderAction
{
    private FormFactoryInterface $formFactory;

    private CartContextInterface $cartContext;

    private FlashBagInterface $flashBag;

    private GiftCardOrPromotionApplicatorInterface $giftCardOrPromotionApplicator;

    private RedirectUrlResolverInterface $redirectRouteResolver;

    private Environment $twig;

    public function __construct(
        FormFactoryInterface $formFactory,
        CartContextInterface $cartContext,
        FlashBagInterface $flashBag,
        GiftCardOrPromotionApplicatorInterface $giftCardOrPromotionApplicator,
        RedirectUrlResolverInterface $redirectRouteResolver,
        Environment $twig
    ) {
        $this->formFactory = $formFactory;
        $this->cartContext = $cartContext;
        $this->flashBag = $flashBag;
        $this->giftCardOrPromotionApplicator = $giftCardOrPromotionApplicator;
        $this->redirectRouteResolver = $redirectRouteResolver;
        $this->twig = $twig;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();

        if (null === $order) {
            throw new NotFoundHttpException();
        }

        $addGiftCardOrPromotionToOrderCommand = new AddGiftCardOrPromotionToOrderCommand();
        $form = $this->formFactory->create(
            AddGiftCardOrPromotionToOrderType::class,
            $addGiftCardOrPromotionToOrderCommand
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $giftCardOrPromotionCode = $addGiftCardOrPromotionToOrderCommand->getCode();
            $this->giftCardOrPromotionApplicator->apply($order, $giftCardOrPromotionCode);

            $this->flashBag->add('success', 'setono_sylius_gift_card.gift_card_added');

            return new RedirectResponse($this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'));
        }

        return new Response($this->twig->render('@SetonoSyliusGiftCardPlugin/Shop/addGiftCardToOrder.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
