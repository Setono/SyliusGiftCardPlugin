<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Resolver;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardProductResolver;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardProductResolverInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class GiftCardProductResolverSpec extends ObjectBehavior
{
    function let(GiftCardRepositoryInterface $giftCardRepository): void
    {
        $this->beConstructedWith($giftCardRepository);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardProductResolver::class);
    }

    function it_implements_gift_card_product_resolver_interface(): void
    {
        $this->shouldHaveType(GiftCardProductResolverInterface::class);
    }

    function it_returns_true_if_the_product_is_gift_card(
        ProductInterface $product,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardInterface $giftCard
    ): void {
        $giftCardRepository->findOneByProduct($product)->willReturn($giftCard);

        $this->isGiftCardProduct($product)->shouldReturn(true);
    }

    function it_returns_false_if_the_product_is_not_gift_card(ProductInterface $product, GiftCardRepositoryInterface $giftCardRepository): void
    {
        $giftCardRepository->findOneByProduct($product)->willReturn(null);

        $this->isGiftCardProduct($product)->shouldReturn(false);
    }
}
