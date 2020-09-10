<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusGiftCardPlugin\Applicator\CouponCodeApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Form\Type\AddGiftCardOrPromotionCouponToOrderType;
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

final class AddGiftCardOrPromotionCouponToOrderAction
{
    /** @var ViewHandlerInterface */
    private $viewHandler;

    /** @var FormFactoryInterface */
    private $formFactory;

    /** @var CartContextInterface */
    private $cartContext;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var GiftCardApplicatorInterface */
    private $giftCardApplicator;

    /** @var RedirectUrlResolverInterface */
    private $redirectRouteResolver;

    /** @var CouponCodeApplicatorInterface */
    private $couponCodeApplicator;

    /** @var bool */
    private $useSameInputForPromotionAndGiftCard;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        FormFactoryInterface $formFactory,
        CartContextInterface $cartContext,
        FlashBagInterface $flashBag,
        GiftCardApplicatorInterface $giftCardApplicator,
        RedirectUrlResolverInterface $redirectRouteResolver,
        CouponCodeApplicatorInterface $couponCodeApplicator,
        bool $useSameInputForPromotionAndGiftCard
    ) {
        $this->viewHandler = $viewHandler;
        $this->formFactory = $formFactory;
        $this->cartContext = $cartContext;
        $this->flashBag = $flashBag;
        $this->giftCardApplicator = $giftCardApplicator;
        $this->redirectRouteResolver = $redirectRouteResolver;
        $this->couponCodeApplicator = $couponCodeApplicator;
        $this->useSameInputForPromotionAndGiftCard = $useSameInputForPromotionAndGiftCard;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();

        if (null === $order) {
            throw new NotFoundHttpException();
        }

        $addGiftCardOrPromotionCouponToOrderCommand = new AddGiftCardOrPromotionCouponToOrderCommand();
        $form = $this->formFactory->create(AddGiftCardOrPromotionCouponToOrderType::class, $addGiftCardOrPromotionCouponToOrderCommand);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $giftCard = $addGiftCardOrPromotionCouponToOrderCommand->getGiftCard();
            $promotionCoupon = $addGiftCardOrPromotionCouponToOrderCommand->getPromotionCoupon();
            if (null !== $giftCard) {
                $this->giftCardApplicator->apply($order, $giftCard);
            } elseif (null !== $promotionCoupon) {
                $couponCode = $promotionCoupon->getCode();
                Assert::notNull($couponCode);
                $this->couponCodeApplicator->apply($order, $couponCode);
            }

            $this->flashBag->add('success', 'setono_sylius_gift_card.gift_card_added');

            if ($request->isXmlHttpRequest()) {
                return $this->viewHandler->handle(View::create([], Response::HTTP_CREATED));
            }

            return new RedirectResponse($this->redirectRouteResolver->getUrlToRedirectTo($request, 'sylius_shop_cart_summary'));
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
