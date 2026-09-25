<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Promotion\Distributor;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Promotion\Distributor\GiftCardExcludingMinimumPriceDistributor;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Distributor\MinimumPriceDistributorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

/**
 * Sylius hands the n-th amount the distributor returns to the n-th item of the order, so the gift card lines keep their
 * places with nothing while the decorated distributor spreads the amount over the other lines
 */
final class GiftCardExcludingMinimumPriceDistributorTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_leaves_an_order_without_gift_cards_to_the_decorated_distributor(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();
        $mug = $this->item(false);
        $shirt = $this->item(false);

        $decorated = $this->prophesize(MinimumPriceDistributorInterface::class);
        $decorated->distribute([$mug, $shirt], -1000, $channel, true)->willReturn([-600, -400]);

        self::assertSame([-600, -400], (new GiftCardExcludingMinimumPriceDistributor($decorated->reveal()))->distribute([$mug, $shirt], -1000, $channel, true));
    }

    /** @test */
    public function it_spreads_the_amount_over_the_other_items_and_gives_the_gift_cards_nothing(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();
        $mug = $this->item(false);
        $giftCard = $this->item(true);
        $shirt = $this->item(false);

        $decorated = $this->prophesize(MinimumPriceDistributorInterface::class);
        $decorated->distribute([$mug, $shirt], -1000, $channel, false)->willReturn([-600, -400]);

        self::assertSame(
            [-600, 0, -400],
            (new GiftCardExcludingMinimumPriceDistributor($decorated->reveal()))->distribute([$mug, $giftCard, $shirt], -1000, $channel, false),
        );
    }

    /** @test */
    public function it_gives_nothing_to_an_order_holding_only_gift_cards(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();

        $decorated = $this->prophesize(MinimumPriceDistributorInterface::class);
        $decorated->distribute(Argument::cetera())->shouldNotBeCalled();

        self::assertSame(
            [0, 0],
            (new GiftCardExcludingMinimumPriceDistributor($decorated->reveal()))->distribute([$this->item(true), $this->item(true)], -1000, $channel, true),
        );
    }

    private function item(bool $giftCard): OrderItemInterface
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        $item = $this->prophesize(OrderItemInterface::class);
        $item->getProduct()->willReturn($product);

        return $item->reveal();
    }
}
