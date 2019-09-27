<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Model;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

class GiftCardSpec extends ObjectBehavior
{
    function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCard::class);
    }

    function it_is_a_resource(): void
    {
        $this->shouldHaveType(ResourceInterface::class);
    }

    function it_implements_gift_card_interface(): void
    {
        $this->shouldHaveType(GiftCardInterface::class);
    }

    function it_allows_access_via_properties(ProductInterface $product): void
    {
        $this->setProduct($product);
        $this->getProduct()->shouldReturn($product);
    }

    function it_associates_gift_card_codes(GiftCardInterface $giftCardCode): void
    {
        $this->addGiftCardCode($giftCardCode);
        $this->hasGiftCardCode($giftCardCode)->shouldReturn(true);

        $this->removeGiftCardCode($giftCardCode);
        $this->hasGiftCardCode($giftCardCode)->shouldReturn(false);
    }
}
