<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardFactory implements GiftCardFactoryInterface
{
    /** @var FactoryInterface */
    private $factory;

    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function createNew(): GiftCardInterface
    {
        /** @var GiftCardInterface $giftCard */
        $giftCard = $this->factory->createNew();

        return $giftCard;
    }

    public function createWithProduct(ProductInterface $product): GiftCardInterface
    {
        $giftCard = $this->createNew();
        $giftCard->setProduct($product);

        return $giftCard;
    }
}
