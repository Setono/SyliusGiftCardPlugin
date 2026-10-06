<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardQuantityFitsCart;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardQuantityFitsCartValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Product as PlainProduct;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The cart page maps the quantities onto the cart before validating it: a raised line holds new units, which are not
 * in the database yet, and the cart's totals already hold them. A gift card line raised past what Sylius' integer
 * columns hold (2147483647 minor units by default, sylius_core.max_int_value) is reported on its quantity field
 *
 * @extends ConstraintValidatorTestCase<GiftCardQuantityFitsCartValidator>
 */
final class GiftCardQuantityFitsCartValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private const MAXIMUM = 2147483647;

    private const MESSAGE = 'setono_sylius_gift_card.order.gift_card_quantity_does_not_fit_cart';

    /** @var ObjectProphecy<LocaleContextInterface> */
    private ObjectProphecy $localeContext;

    /** @var ObjectProphecy<MoneyFormatterInterface> */
    private ObjectProphecy $moneyFormatter;

    /** The id the next unit saved with the cart before gets */
    private int $nextUnitId = 1;

    /** @test */
    public function it_reports_a_raised_gift_card_line_on_its_quantity_when_the_cart_no_longer_fits(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 2_000_000_000, persisted: 1, added: 1);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[0].quantity')
            ->assertRaised();
    }

    /**
     * The cart form names a line's fields by the line's key in the cart's items, which a removed line leaves a gap in
     *
     * @test
     */
    public function it_names_the_line_by_its_key_in_the_cart(): void
    {
        $cart = $this->cart();
        $removed = $this->addLine($cart, $this->product(giftCard: false), 100, persisted: 1, added: 0);
        $this->addLine($cart, $this->product(giftCard: false), 100, persisted: 1, added: 0);
        $this->addLine($cart, $this->giftCardProduct(), 2_000_000_000, persisted: 1, added: 1);
        $cart->removeItem($removed);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[2].quantity')
            ->assertRaised();
    }

    /** @test */
    public function it_reports_every_raised_gift_card_line(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 1_000_000_000, persisted: 1, added: 1);
        $this->addLine($cart, $this->giftCardProduct(), 100_000_000, persisted: 1, added: 1);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[0].quantity')
            ->buildNextViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[1].quantity')
            ->assertRaised();
    }

    /**
     * @test
     *
     * @dataProvider cartsTheRaisedLineFits
     */
    public function it_accepts_a_raised_gift_card_line_the_cart_has_room_for(int $unitPrice, int $persisted, int $added): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), $unitPrice, $persisted, $added);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function cartsTheRaisedLineFits(): iterable
    {
        yield 'a cart raised to just below the maximum' => [1_073_741_823, 1, 1];
        yield 'an ordinary quantity' => [5000, 1, 4];
    }

    /** @test */
    public function it_accepts_a_cart_raised_exactly_to_the_maximum(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->product(giftCard: false), 1, persisted: 1, added: 0);
        $this->addLine($cart, $this->giftCardProduct(), 1_073_741_823, persisted: 1, added: 1);

        self::assertSame(self::MAXIMUM, $cart->getItemsTotal(), 'precondition');

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->assertNoViolation();
    }

    /**
     * Shipping takes the order total above the items total
     *
     * @test
     */
    public function it_holds_the_order_total_when_shipping_takes_it_above_the_items_total(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 1_073_741_823, persisted: 1, added: 1);
        $this->adjust($cart, AdjustmentInterface::SHIPPING_ADJUSTMENT, 500);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[0].quantity')
            ->assertRaised();
    }

    /**
     * An order discount takes the order total below the items total
     *
     * @test
     */
    public function it_holds_the_items_total_when_an_order_discount_takes_the_total_below_it(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 1_073_741_824, persisted: 1, added: 1);
        $this->adjust($cart, AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT, -1000);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->atPath('property.path.items[0].quantity')
            ->assertRaised();
    }

    /**
     * A cart that overflows through another product does so without the plugin as well, which makes it Sylius' to
     * guard. A gift card line that was not raised is left alone with it
     *
     * @test
     *
     * @dataProvider productsThatAreNotGiftCards
     */
    public function it_leaves_a_cart_raised_through_another_product_alone(ProductInterface $product): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 2_000_000_000, persisted: 1, added: 0);
        $this->addLine($cart, $product, 300_000, persisted: 1, added: 9998);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{ProductInterface}>
     */
    public static function productsThatAreNotGiftCards(): iterable
    {
        $product = new Product();
        $product->setGiftCard(false);

        yield 'a product that is not a gift card' => [$product];

        // an application whose product class does not implement the plugin's product interface
        yield 'a product without the gift card flag' => [new PlainProduct()];
    }

    /**
     * Only a line raised in this request is reported. One that holds no new units is left alone whatever the totals
     *
     * @test
     */
    public function it_leaves_a_gift_card_line_that_was_not_raised_alone(): void
    {
        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 1_500_000_000, persisted: 2, added: 0);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->assertNoViolation();
    }

    /**
     * An application that widened Sylius' columns raises sylius_core.max_int_value with them
     *
     * @test
     */
    public function it_holds_the_cart_to_the_maximum_it_is_given(): void
    {
        $validator = new GiftCardQuantityFitsCartValidator(4_000_000_000, $this->moneyFormatter->reveal(), $this->localeContext->reveal());
        $validator->initialize($this->context);

        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 2_000_000_000, persisted: 1, added: 1);

        $validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->assertNoViolation();
    }

    /**
     * Validation also runs where no locale is being browsed, such as a console command or a host application's API
     *
     * @test
     */
    public function it_quotes_the_maximum_without_a_locale_when_none_can_be_resolved(): void
    {
        $this->localeContext->getLocaleCode()->willThrow(new LocaleNotFoundException());

        $cart = $this->cart();
        $this->addLine($cart, $this->giftCardProduct(), 2_000_000_000, persisted: 1, added: 1);

        $this->validator->validate($cart, new GiftCardQuantityFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', 'DKK 21,474,836.47')
            ->atPath('property.path.items[0].quantity')
            ->assertRaised();
    }

    /** @test */
    public function it_only_validates_an_order(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new AddToCartCommand($this->cart(), new OrderItem(), new GiftCardInformation()), new GiftCardQuantityFitsCart());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->cart(), new NotBlank());
    }

    protected function createValidator(): GiftCardQuantityFitsCartValidator
    {
        $this->localeContext = $this->prophesize(LocaleContextInterface::class);
        $this->localeContext->getLocaleCode()->willReturn('da_DK');

        // What Sylius' money formatter makes of the maximum in the cart's currency, browsed in Danish and without a locale
        $this->moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $this->moneyFormatter->format(self::MAXIMUM, 'DKK', 'da_DK')->willReturn('21.474.836,47 kr.');
        $this->moneyFormatter->format(self::MAXIMUM, 'DKK', null)->willReturn('DKK 21,474,836.47');

        return new GiftCardQuantityFitsCartValidator(self::MAXIMUM, $this->moneyFormatter->reveal(), $this->localeContext->reveal());
    }

    private function cart(): Order
    {
        $cart = new Order();
        $cart->setCurrencyCode('DKK');

        return $cart;
    }

    /**
     * A line of the product at the unit price, holding units that were saved with the cart before and units the cart
     * page has just added
     */
    private function addLine(Order $cart, ProductInterface $product, int $unitPrice, int $persisted, int $added): OrderItem
    {
        $variant = new ProductVariant();
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        $cart->addItem($item);

        for ($i = 0; $i < $persisted; ++$i) {
            $unit = new OrderItemUnit($item);
            (new \ReflectionProperty(OrderItemUnit::class, 'id'))->setValue($unit, $this->nextUnitId++);
        }

        for ($i = 0; $i < $added; ++$i) {
            new OrderItemUnit($item);
        }

        return $item;
    }

    private function giftCardProduct(): Product
    {
        return $this->product(giftCard: true);
    }

    private function product(bool $giftCard): Product
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        return $product;
    }

    private function adjust(Order $cart, string $type, int $amount): void
    {
        $adjustment = new Adjustment();
        $adjustment->setType($type);
        $adjustment->setAmount($amount);

        $cart->addAdjustment($adjustment);
    }
}
