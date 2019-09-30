<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;

final class GiftCardContext implements Context
{
    /** @var GiftCardApplicatorInterface */
    private $giftCardApplicator;

    /** @var CartContextInterface */
    private $cartContext;

    public function __construct(
        GiftCardApplicatorInterface $giftCardApplicator,
        CartContextInterface $cartContext
    ) {
        $this->giftCardApplicator = $giftCardApplicator;
        $this->cartContext = $cartContext;
    }

    /**
     * @When I have applied gift card :giftCard
     */
    public function iApplyGiftCard(GiftCardInterface $giftCard): void
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $this->giftCardApplicator->apply($order, $giftCard);
    }
}
