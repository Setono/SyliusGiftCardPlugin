<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Entity\GiftCard;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class GiftCardFactory implements GiftCardFactoryInterface
{
    public function createWithProduct(ProductInterface $product): GiftCardInterface
    {
        $giftCard = $this->createNew();

        $giftCard->setProduct($product);

        return $giftCard;
    }

    public function createNew(): GiftCardInterface
    {
        return new GiftCard();
    }
}
