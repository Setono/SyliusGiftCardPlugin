<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Controller\Action;

use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class RemoveGiftCardFromOrderAction
{
    /** @var CartContextInterface */
    private $cartContext;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var GiftCardApplicatorInterface */
    private $giftCardApplicator;

    public function __construct(
        CartContextInterface $cartContext,
        FlashBagInterface $flashBag,
        UrlGeneratorInterface $urlGenerator,
        GiftCardApplicatorInterface $giftCardApplicator
    ) {
        $this->cartContext = $cartContext;
        $this->flashBag = $flashBag;
        $this->urlGenerator = $urlGenerator;
        $this->giftCardApplicator = $giftCardApplicator;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface|null $order */
        $order = $this->cartContext->getCart();
        Assert::notNull($order);

        $this->giftCardApplicator->remove($order, $request->attributes->get('giftCard'));

        $this->flashBag->add('success', 'setono_sylius_gift_card.gift_card_removed');

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_cart_summary'));
    }
}
