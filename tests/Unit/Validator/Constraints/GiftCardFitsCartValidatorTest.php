<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Tests\Unit\Currency\FixedRateCurrencyConverter;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardFitsCart;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardFitsCartValidator;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Channel;
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
 * A gift card is put in the cart at the price the customer chose, so the line it adds is the amount times the quantity.
 * The cart's totals have to hold it, as Sylius keeps them in integer columns: 2147483647 minor units by default
 * (sylius_core.max_int_value)
 *
 * @extends ConstraintValidatorTestCase<GiftCardFitsCartValidator>
 */
final class GiftCardFitsCartValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private const MAXIMUM = 2147483647;

    private const MESSAGE = 'setono_sylius_gift_card.add_to_cart_command.gift_card_does_not_fit_cart';

    /** @var ObjectProphecy<LocaleContextInterface> */
    private ObjectProphecy $localeContext;

    /** @var ObjectProphecy<MoneyFormatterInterface> */
    private ObjectProphecy $moneyFormatter;

    /** @var ObjectProphecy<GiftCardCurrencyProviderInterface> */
    private ObjectProphecy $currencyProvider;

    /** @test */
    public function it_refuses_a_gift_card_the_cart_has_no_room_left_for(): void
    {
        $this->validator->validate($this->command(cartTotal: self::MAXIMUM, amount: 100), new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * Two cards each fit a gift card's balance, but not the cart together
     *
     * @test
     */
    public function it_refuses_gift_cards_that_do_not_fit_the_cart_at_the_quantity_chosen(): void
    {
        $this->validator->validate($this->command(cartTotal: 0, amount: 2_000_000_000, quantity: 2), new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * @test
     *
     * @dataProvider cartsTheGiftCardFits
     */
    public function it_accepts_a_gift_card_the_cart_has_room_for(int $cartTotal, int $amount, int $quantity): void
    {
        $this->validator->validate($this->command($cartTotal, $amount, $quantity), new GiftCardFitsCart());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function cartsTheGiftCardFits(): iterable
    {
        yield 'an empty cart and the most a card can hold' => [0, self::MAXIMUM, 1];
        yield 'a cart taken exactly to the maximum' => [self::MAXIMUM - 100, 100, 1];
        yield 'a cart taken exactly to the maximum by the quantity' => [self::MAXIMUM - 300, 100, 3];
        yield 'an ordinary cart' => [12500, 5000, 2];
    }

    /**
     * Shipping takes the order total above the items total. The card would fit the items total, not the order total
     *
     * @test
     */
    public function it_holds_the_order_total_when_shipping_takes_it_above_the_items_total(): void
    {
        $command = $this->command(cartTotal: self::MAXIMUM - 1000, amount: 600);
        $this->adjustCart($command, AdjustmentInterface::SHIPPING_ADJUSTMENT, 500);

        $this->validator->validate($command, new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * An order discount takes the order total below the items total. The card would fit the order total, not the
     * items total
     *
     * @test
     */
    public function it_holds_the_items_total_when_an_order_discount_takes_the_total_below_it(): void
    {
        $command = $this->command(cartTotal: self::MAXIMUM - 500, amount: 600);
        $this->adjustCart($command, AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT, -1000);

        $this->validator->validate($command, new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * The admin sets an ordinary product's price. At a high enough price and quantity it overflows the cart all the
     * same, but it does so without the plugin as well, which makes it Sylius' to guard
     *
     * @test
     *
     * @dataProvider productsThatAreNotGiftCards
     */
    public function it_leaves_any_other_product_alone(ProductInterface $product): void
    {
        $this->validator->validate($this->command(cartTotal: self::MAXIMUM, amount: 100, product: $product), new GiftCardFitsCart());

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
     * The add to cart form leaves the line without a variant when a request leaves out the variant choice, and Sylius'
     * order item reads its product off its variant. CartItemVariantRequired reports the missing variant, once
     *
     * @test
     */
    public function it_leaves_a_line_without_a_variant_to_the_variant_check(): void
    {
        // a cart that has no room left, so only the missing variant keeps it from being reported
        $command = $this->command(cartTotal: self::MAXIMUM, amount: 100);

        $this->validator->validate(new AddToCartCommand($command->getCart(), new OrderItem(), $command->getGiftCardInformation()), new GiftCardFitsCart());

        $this->assertNoViolation();
    }

    /**
     * NotBlank reports a blank amount on the amount field
     *
     * @test
     */
    public function it_leaves_a_blank_amount_to_the_amount_field(): void
    {
        $this->validator->validate($this->command(cartTotal: self::MAXIMUM, amount: null), new GiftCardFitsCart());

        $this->assertNoViolation();
    }

    /**
     * An amount no line's unit price can hold is too large for a card, wherever it is put. That is the amount's own
     * error, and the customer is told once
     *
     * @test
     */
    public function it_leaves_an_amount_no_line_can_hold_to_the_amount_field(): void
    {
        $this->validator->validate($this->command(cartTotal: 0, amount: self::MAXIMUM + 1), new GiftCardFitsCart());

        $this->assertNoViolation();
    }

    /**
     * A card holding the most a card can is no error of its own, but it fits nothing but an empty cart
     *
     * @test
     */
    public function it_refuses_the_largest_card_for_a_cart_that_is_not_empty(): void
    {
        $this->validator->validate($this->command(cartTotal: 100, amount: self::MAXIMUM), new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * A card held in another currency than the cart's costs its amount converted into the cart's currency (#488), which
     * is what has to fit: $3,000,000.00 is a card's worth, but DKK 22,380,000.00 is more than the cart holds
     *
     * @test
     */
    public function it_holds_the_cart_to_the_price_of_a_card_in_another_currency_converted_into_the_carts(): void
    {
        $this->validator->validate($this->command(cartTotal: 0, amount: 300_000_000, currencyCode: 'USD'), new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * Information an application builds itself may not name its currency, which is then the one the customer browses
     * in, the one the amount field was in: dollars here, on a cart in a channel kept in kroner
     *
     * @test
     */
    public function it_takes_the_amount_to_be_in_the_currency_browsed_in_when_the_information_does_not_say(): void
    {
        $command = $this->command(cartTotal: 0, amount: 300_000_000);
        $command->getCart()->setChannel(new Channel());

        $this->validator->validate($command, new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', '21.474.836,47 kr.')
            ->assertRaised();
    }

    /**
     * $2,800,000.00 is DKK 20,888,000.00, which the cart holds
     *
     * @test
     */
    public function it_accepts_a_card_in_another_currency_whose_converted_price_the_cart_has_room_for(): void
    {
        $this->validator->validate($this->command(cartTotal: 0, amount: 280_000_000, currencyCode: 'USD'), new GiftCardFitsCart());

        $this->assertNoViolation();
    }

    /**
     * An application that widened Sylius' columns raises sylius_core.max_int_value with them
     *
     * @test
     */
    public function it_holds_the_cart_to_the_maximum_it_is_given(): void
    {
        $validator = new GiftCardFitsCartValidator(4_000_000_000, $this->moneyFormatter->reveal(), $this->localeContext->reveal(), new FixedRateCurrencyConverter(), $this->currencyProvider->reveal());
        $validator->initialize($this->context);

        $validator->validate($this->command(cartTotal: 0, amount: 2_000_000_000, quantity: 2), new GiftCardFitsCart());

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

        $this->validator->validate($this->command(cartTotal: self::MAXIMUM, amount: 100), new GiftCardFitsCart());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ maximum }}', 'DKK 21,474,836.47')
            ->assertRaised();
    }

    /** @test */
    public function it_only_validates_an_add_to_cart_command(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new Order(), new GiftCardFitsCart());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->command(cartTotal: 0, amount: 100), new NotBlank());
    }

    protected function createValidator(): GiftCardFitsCartValidator
    {
        $this->localeContext = $this->prophesize(LocaleContextInterface::class);
        $this->localeContext->getLocaleCode()->willReturn('da_DK');

        // What Sylius' money formatter makes of the maximum in the cart's currency, browsed in Danish and without a locale
        $this->moneyFormatter = $this->prophesize(MoneyFormatterInterface::class);
        $this->moneyFormatter->format(self::MAXIMUM, 'DKK', 'da_DK')->willReturn('21.474.836,47 kr.');
        $this->moneyFormatter->format(self::MAXIMUM, 'DKK', null)->willReturn('DKK 21,474,836.47');

        // the customer browses in dollars, unless a test says otherwise
        $this->currencyProvider = $this->prophesize(GiftCardCurrencyProviderInterface::class);
        $this->currencyProvider->getCurrencyCode(Argument::any())->willReturn('USD');

        // kroner at 7.46 to the dollar
        return new GiftCardFitsCartValidator(self::MAXIMUM, $this->moneyFormatter->reveal(), $this->localeContext->reveal(), new FixedRateCurrencyConverter(), $this->currencyProvider->reveal());
    }

    /**
     * What the shop's add to cart form holds once submitted: a cart already holding an item of the given total, and a
     * new line of the product at the quantity chosen, with the amount chosen. The line is not in the cart yet
     */
    private function command(int $cartTotal, ?int $amount, int $quantity = 1, ?ProductInterface $product = null, ?string $currencyCode = null): AddToCartCommand
    {
        $cart = new Order();
        $cart->setCurrencyCode('DKK');

        if (0 < $cartTotal) {
            $existing = new OrderItem();
            $existing->setUnitPrice($cartTotal);
            new OrderItemUnit($existing);
            $cart->addItem($existing);
        }

        if (null === $product) {
            $product = new Product();
            $product->setGiftCard(true);
        }

        $variant = new ProductVariant();
        $product->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        for ($i = 0; $i < $quantity; ++$i) {
            new OrderItemUnit($item);
        }

        $information = new GiftCardInformation($amount);
        $information->setCurrencyCode($currencyCode);

        return new AddToCartCommand($cart, $item, $information);
    }

    private function adjustCart(AddToCartCommand $command, string $type, int $amount): void
    {
        $adjustment = new Adjustment();
        $adjustment->setType($type);
        $adjustment->setAmount($amount);

        $command->getCart()->addAdjustment($adjustment);
    }
}
