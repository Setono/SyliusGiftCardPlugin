<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Sylius\Component\Core\Factory\PromotionActionFactoryInterface;
use Sylius\Component\Core\Factory\PromotionRuleFactoryInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\PromotionInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Promotion\Model\PromotionActionInterface;
use Sylius\Component\Promotion\Model\PromotionRuleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * A gift card is worth the amount the customer chose, so no promotion may discount its line (#116). Otherwise a 50 %
 * coupon sells a 50.00 card for 25.00: the card would either be worth 25.00, which is not what the customer bought,
 * or 50.00, which would let a coupon for the whole shop buy full value gift cards at half price.
 *
 * Each case runs a cart holding a 50.00 gift card and a 30.00 mug through Sylius' own order processing, with one of
 * the discounts Sylius ships
 */
final class GiftCardPromotionTest extends GiftCardFunctionalTestCase
{
    private const GIFT_CARD_PRICE = 5000;

    private const MUG_PRICE = 3000;

    /** @test */
    public function an_order_percentage_discount_takes_its_percentage_of_the_other_items_only(): void
    {
        $this->createPromotion('HALF_OFF', $this->actions()->createPercentageDiscount(0.5));

        [$order, $giftCard, $mug] = $this->createCart();

        self::assertSame(0, self::promotionTotal($giftCard));
        self::assertSame(self::GIFT_CARD_PRICE, $giftCard->getTotal());
        // half of the mug, not half of the whole cart piled onto the mug
        self::assertSame(-1500, self::promotionTotal($mug));
        self::assertSame(self::GIFT_CARD_PRICE + 1500, $order->getTotal());
    }

    /** @test */
    public function an_order_fixed_discount_is_taken_from_the_other_items_and_never_exceeds_them(): void
    {
        $this->createPromotion('FORTY_OFF', $this->actions()->createFixedDiscount(4000, $this->channelCode()));

        [$order, $giftCard, $mug] = $this->createCart();

        self::assertSame(0, self::promotionTotal($giftCard));
        // 40.00 off, but there is only 30.00 that can be discounted
        self::assertSame(-self::MUG_PRICE, self::promotionTotal($mug));
        self::assertSame(self::GIFT_CARD_PRICE, $order->getTotal());
    }

    /** @test */
    public function a_unit_percentage_discount_skips_the_gift_card_line(): void
    {
        $this->createPromotion('HALF_OFF_EACH', $this->actions()->createUnitPercentageDiscount(0.5, $this->channelCode()));

        [$order, $giftCard, $mug] = $this->createCart();

        self::assertSame(0, self::promotionTotal($giftCard));
        self::assertSame(-1500, self::promotionTotal($mug));
        self::assertSame(self::GIFT_CARD_PRICE + 1500, $order->getTotal());
    }

    /** @test */
    public function a_unit_fixed_discount_skips_the_gift_card_line(): void
    {
        $this->createPromotion('TEN_OFF_EACH', $this->actions()->createUnitFixedDiscount(1000, $this->channelCode()));

        [$order, $giftCard, $mug] = $this->createCart();

        self::assertSame(0, self::promotionTotal($giftCard));
        self::assertSame(-1000, self::promotionTotal($mug));
        self::assertSame(self::GIFT_CARD_PRICE + 2000, $order->getTotal());
    }

    /**
     * Buying a gift card is paying in advance, not spending, so it does not help a cart reach a promotion's minimum
     *
     * @test
     */
    public function buying_a_gift_card_does_not_count_towards_an_item_total_rule(): void
    {
        // the cart holds 80.00 of items, but only the 30.00 mug counts
        $this->createPromotion(
            'SPEND_60',
            $this->actions()->createPercentageDiscount(0.1),
            $this->rules()->createItemTotal($this->channelCode(), 6000),
        );

        [$order, $giftCard, $mug] = $this->createCart();

        self::assertSame(0, self::promotionTotal($giftCard));
        self::assertSame(0, self::promotionTotal($mug));
        self::assertSame(self::GIFT_CARD_PRICE + self::MUG_PRICE, $order->getTotal());
    }

    /** @test */
    public function a_cart_holding_only_a_gift_card_gets_no_discount(): void
    {
        $this->createPromotion('HALF_OFF', $this->actions()->createPercentageDiscount(0.5));

        $order = $this->createOrder();
        $giftCard = $this->addGiftCardLine($order);
        $this->process($order);

        self::assertSame(0, self::promotionTotal($giftCard));
        self::assertSame(self::GIFT_CARD_PRICE, $order->getTotal());
    }

    /**
     * @return array{Order, OrderItem, OrderItem} the processed cart, its gift card line and its mug line
     */
    private function createCart(): array
    {
        $order = $this->createOrder();
        $giftCard = $this->addGiftCardLine($order);
        $mug = $this->addItem($order, 'MUG', self::MUG_PRICE);

        $this->process($order);

        return [$order, $giftCard, $mug];
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        return $order;
    }

    /**
     * A line the way CartGiftCardHandler leaves it: priced at the amount the customer chose and immutable, so cart
     * processing does not reprice it
     */
    private function addGiftCardLine(Order $order): OrderItem
    {
        $item = $this->addItem($order, 'GIFT_CARD', self::GIFT_CARD_PRICE, giftCard: true);
        $item->setImmutable(true);

        return $item;
    }

    private function process(Order $order): void
    {
        /** @var OrderProcessorInterface $processor */
        $processor = self::getContainer()->get('sylius.order_processing.order_processor');
        $processor->process($order);

        $this->manager->persist($order);
        $this->manager->flush();
    }

    private function createPromotion(string $code, PromotionActionInterface $action, ?PromotionRuleInterface $rule = null): void
    {
        /** @var FactoryInterface<PromotionInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.promotion');

        $promotion = $factory->createNew();
        $promotion->setCode($code);
        $promotion->setName($code);
        $promotion->addChannel($this->getChannel());
        $promotion->addAction($action);
        if (null !== $rule) {
            $promotion->addRule($rule);
        }

        $this->manager->persist($promotion);
        $this->manager->flush();
    }

    /**
     * What promotions took off the line, whether as an order discount spread over its units or a unit discount
     */
    private static function promotionTotal(OrderItem $item): int
    {
        return $item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) +
            $item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT);
    }

    private function channelCode(): string
    {
        return (string) $this->getChannel()->getCode();
    }

    /**
     * @return PromotionActionFactoryInterface<PromotionActionInterface>
     */
    private function actions(): PromotionActionFactoryInterface
    {
        /** @var PromotionActionFactoryInterface<PromotionActionInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.promotion_action');

        return $factory;
    }

    /**
     * @return PromotionRuleFactoryInterface<PromotionRuleInterface>
     */
    private function rules(): PromotionRuleFactoryInterface
    {
        /** @var PromotionRuleFactoryInterface<PromotionRuleInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.promotion_rule');

        return $factory;
    }
}
