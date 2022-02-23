<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

final class ExampleGiftCardFactory implements ExampleGiftCardFactoryInterface
{
    private GiftCardFactoryInterface $giftCardFactory;

    private CurrencyContextInterface $currencyContext;

    public function __construct(GiftCardFactoryInterface $giftCardFactory, CurrencyContextInterface $currencyContext)
    {
        $this->giftCardFactory = $giftCardFactory;
        $this->currencyContext = $currencyContext;
    }

    public function createNew(): GiftCardInterface
    {
        $giftCard = $this->giftCardFactory->createNew();
        $giftCard->setAmount(1500);
        $giftCard->setCurrencyCode($this->currencyContext->getCurrencyCode());

        return $giftCard;
    }
}
