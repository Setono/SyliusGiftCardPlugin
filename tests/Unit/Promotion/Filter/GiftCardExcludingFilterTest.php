<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Promotion\Filter;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Promotion\Filter\GiftCardExcludingFilter;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product as CoreProduct;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;

/**
 * Unit discounts run Sylius' product filter whatever the promotion filters on, so dropping the gift card lines there
 * keeps every unit discount off them
 */
final class GiftCardExcludingFilterTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_drops_the_gift_card_lines_from_what_the_decorated_filter_keeps(): void
    {
        $giftCard = $this->item(self::product(true));
        $mug = $this->item(self::product(false));
        $excludedByTheDecorated = $this->item(self::product(false));

        $decorated = $this->prophesize(FilterInterface::class);
        $decorated->filter([$giftCard, $mug, $excludedByTheDecorated], ['products_filter' => []])->willReturn([$giftCard, $mug]);

        $filtered = (new GiftCardExcludingFilter($decorated->reveal()))->filter([$giftCard, $mug, $excludedByTheDecorated], ['products_filter' => []]);

        self::assertSame([$mug], array_values($filtered));
    }

    /**
     * A product class without the plugin's trait, and a line without a product, cannot be a gift card
     *
     * @test
     */
    public function it_keeps_lines_it_cannot_tell_to_be_gift_cards(): void
    {
        $coreProduct = $this->item(new CoreProduct());
        $noProduct = $this->item(null);

        $decorated = $this->prophesize(FilterInterface::class);
        $decorated->filter([$coreProduct, $noProduct], [])->willReturn([$coreProduct, $noProduct]);

        self::assertSame([$coreProduct, $noProduct], (new GiftCardExcludingFilter($decorated->reveal()))->filter([$coreProduct, $noProduct], []));
    }

    private function item(?CoreProduct $product): OrderItemInterface
    {
        $item = $this->prophesize(OrderItemInterface::class);
        $item->getProduct()->willReturn($product);

        return $item->reveal();
    }

    private static function product(bool $giftCard): Product
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        return $product;
    }
}
