<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCartItemAvailability;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCartItemAvailabilityValidator;
use Sylius\Component\Core\Model\Product as PlainProduct;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Gift card lines never merge, so Sylius' stock checks on add to cart, which look at one line, miss the cards of the
 * variant the cart holds in its other lines. The validator counts them, and leaves to Sylius what one line can check
 *
 * @extends ConstraintValidatorTestCase<GiftCardCartItemAvailabilityValidator>
 */
final class GiftCardCartItemAvailabilityValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private ?AvailabilityCheckerInterface $availabilityChecker = null;

    /** @test */
    public function it_refuses_gift_cards_that_with_the_carts_other_lines_of_the_variant_are_more_than_is_in_stock(): void
    {
        $variant = self::variant(onHand: 5);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 4)]), new GiftCardCartItemAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_counts_every_other_line_of_the_variant(): void
    {
        $variant = self::variant(onHand: 5);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 2), self::line($variant, 2)]), new GiftCardCartItemAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_adds_gift_cards_up_to_what_the_other_lines_leave_in_stock(): void
    {
        $variant = self::variant(onHand: 5);

        $this->validator->validate(self::command($variant, 1, [self::line($variant, 2), self::line($variant, 2)]), new GiftCardCartItemAvailability());

        $this->assertNoViolation();
    }

    /**
     * What orders placed before hold is not in stock any more, as Sylius counts it
     *
     * @test
     */
    public function it_leaves_out_what_placed_orders_hold(): void
    {
        $variant = self::variant(onHand: 5, onHold: 2);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 2)]), new GiftCardCartItemAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_does_not_count_the_lines_of_other_variants(): void
    {
        $variant = self::variant(onHand: 5);

        $this->validator->validate(self::command($variant, 2, [self::line(self::variant(onHand: 5), 4)]), new GiftCardCartItemAvailability());

        $this->assertNoViolation();
    }

    /**
     * Sylius checks the line on its own: the add to cart form's CartItemAvailability, which finds no line to merge
     * it with, and the line's InStock once the form is valid. Reporting it here as well would report it twice
     *
     * @test
     *
     * @dataProvider linesSyliusChecks
     *
     * @param list<int> $otherLines
     */
    public function it_leaves_what_one_line_can_check_to_sylius(int $quantity, array $otherLines): void
    {
        $variant = self::variant(onHand: 5);

        $this->validator->validate(
            self::command($variant, $quantity, array_map(static fn (int $otherLine): OrderItem => self::line($variant, $otherLine), $otherLines)),
            new GiftCardCartItemAvailability(),
        );

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function linesSyliusChecks(): iterable
    {
        yield 'a cart without another line of the variant' => [6, []];
        yield 'a line out of stock on its own' => [6, [1]];
    }

    /** @test */
    public function it_does_not_limit_a_variant_that_is_not_tracked(): void
    {
        $variant = self::variant(onHand: null);

        $this->validator->validate(self::command($variant, 200, [self::line($variant, 400)]), new GiftCardCartItemAvailability());

        $this->assertNoViolation();
    }

    /**
     * Lines of any other product merge, and Sylius' own check counts the line they merge with
     *
     * @test
     *
     * @dataProvider productsThatAreNotGiftCards
     */
    public function it_leaves_any_other_product_to_sylius(ProductInterface $product): void
    {
        $variant = self::variant(onHand: 5, product: $product);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 4)]), new GiftCardCartItemAvailability());

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
     * A product without a name in the locale being browsed has no inventory name
     *
     * @test
     */
    public function it_names_a_product_without_a_name_by_nothing(): void
    {
        $variant = self::variant(onHand: 5, name: null);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 4)]), new GiftCardCartItemAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', '')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_has_nothing_to_check_for_a_line_without_a_variant(): void
    {
        $command = new AddToCartCommand(new Order(), new OrderItem(), new GiftCardInformation());

        $this->validator->validate($command, new GiftCardCartItemAvailability());

        $this->assertNoViolation();
    }

    /**
     * The stock is counted by the shop's availability checker, the one Sylius' own checks use, so a checker of the
     * application's counts here too
     *
     * @test
     */
    public function it_asks_the_availability_checker_about_every_card_of_the_variant(): void
    {
        $variant = self::variant(onHand: null);

        $availabilityChecker = $this->prophesize(AvailabilityCheckerInterface::class);
        $availabilityChecker->isStockSufficient($variant, 2)->willReturn(true);
        $availabilityChecker->isStockSufficient($variant, 6)->willReturn(false);
        $this->availabilityChecker = $availabilityChecker->reveal();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate(self::command($variant, 2, [self::line($variant, 4)]), new GiftCardCartItemAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(self::command(self::variant(onHand: 5), 1, []), new NotBlank());
    }

    /** @test */
    public function it_only_validates_an_add_to_cart_command(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new Order(), new GiftCardCartItemAvailability());
    }

    protected function createValidator(): GiftCardCartItemAvailabilityValidator
    {
        return new GiftCardCartItemAvailabilityValidator($this->availabilityChecker ?? new AvailabilityChecker());
    }

    /**
     * @param list<OrderItem> $otherLines what the cart holds already
     */
    private static function command(ProductVariant $variant, int $quantity, array $otherLines): AddToCartCommand
    {
        $cart = new Order();
        foreach ($otherLines as $otherLine) {
            $cart->addItem($otherLine);
        }

        return new AddToCartCommand($cart, self::line($variant, $quantity), new GiftCardInformation());
    }

    private static function line(ProductVariant $variant, int $quantity): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($variant);
        for ($i = 0; $i < $quantity; ++$i) {
            new OrderItemUnit($item);
        }

        return $item;
    }

    /**
     * A variant of a gift card product unless another product is given, tracked when it is given a stock
     */
    private static function variant(?int $onHand, int $onHold = 0, ?ProductInterface $product = null, ?string $name = 'Gift card'): ProductVariant
    {
        if (null === $product) {
            $product = new Product();
            $product->setGiftCard(true);
        }
        $product->setCurrentLocale('en_US');
        if (null !== $name) {
            $product->setName($name);
        }

        $variant = new ProductVariant();
        if (null !== $onHand) {
            $variant->setTracked(true);
            $variant->setOnHand($onHand);
            $variant->setOnHold($onHold);
        }
        $product->addVariant($variant);

        return $variant;
    }
}
