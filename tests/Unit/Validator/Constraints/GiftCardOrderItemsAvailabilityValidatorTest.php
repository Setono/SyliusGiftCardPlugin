<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardOrderItemsAvailability;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardOrderItemsAvailabilityValidator;
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
 * Gift card lines never merge, so Sylius' stock check on every order item never sees all the cards of a variant
 * together. The validator checks every tracked gift card variant against all of its lines, and leaves to Sylius what
 * one line can check
 *
 * @extends ConstraintValidatorTestCase<GiftCardOrderItemsAvailabilityValidator>
 */
final class GiftCardOrderItemsAvailabilityValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private ?AvailabilityCheckerInterface $availabilityChecker = null;

    /** @test */
    public function it_refuses_an_order_whose_gift_card_lines_of_a_variant_hold_more_than_is_in_stock_together(): void
    {
        $variant = self::variant('Gift card', onHand: 5);

        $this->validator->validate(self::order(self::line($variant, 4), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_accepts_gift_card_lines_that_are_in_stock_together(): void
    {
        $variant = self::variant('Gift card', onHand: 5);

        $this->validator->validate(self::order(self::line($variant, 3), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

        $this->assertNoViolation();
    }

    /**
     * What orders placed before hold is not in stock any more, as Sylius counts it
     *
     * @test
     */
    public function it_leaves_out_what_placed_orders_hold(): void
    {
        $variant = self::variant('Gift card', onHand: 5, onHold: 2);

        $this->validator->validate(self::order(self::line($variant, 2), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /**
     * The violation is the order's, as no line is at fault on its own, so a variant is reported once however many
     * lines it has. The order's other lines do not get in the way
     *
     * @test
     */
    public function it_reports_every_variant_short_of_stock_once(): void
    {
        $printed = self::variant('Printed gift card', onHand: 5);
        $boxed = self::variant('Boxed gift card', onHand: 2);
        $virtual = self::variant('Virtual gift card', onHand: 5);
        $product = new Product();
        $product->setGiftCard(false);
        $mug = self::variant('Mug', onHand: 5, product: $product);

        $this->validator->validate(self::order(
            self::line($mug, 1),
            new OrderItem(),
            self::line($printed, 2),
            self::line($boxed, 1),
            self::line($printed, 2),
            self::line($virtual, 3),
            self::line($boxed, 2),
            self::line($printed, 2),
        ), new GiftCardOrderItemsAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Printed gift card')
            ->buildNextViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Boxed gift card')
            ->assertRaised()
        ;
    }

    /**
     * Sylius' InStock on every order item reports a line that is out of stock on its own, on that line. Reporting
     * its variant here as well would report it twice
     *
     * @test
     *
     * @dataProvider linesSyliusChecks
     *
     * @param list<int> $quantities
     */
    public function it_leaves_what_one_line_can_check_to_sylius(array $quantities): void
    {
        $variant = self::variant('Gift card', onHand: 5);

        $this->validator->validate(
            self::order(...array_map(static fn (int $quantity): OrderItem => self::line($variant, $quantity), $quantities)),
            new GiftCardOrderItemsAvailability(),
        );

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public static function linesSyliusChecks(): iterable
    {
        yield 'a single line' => [[6]];
        yield 'a line out of stock on its own' => [[6, 1]];
    }

    /** @test */
    public function it_does_not_limit_a_variant_that_is_not_tracked(): void
    {
        $variant = self::variant('Gift card', onHand: null);

        $this->validator->validate(self::order(self::line($variant, 400), self::line($variant, 200)), new GiftCardOrderItemsAvailability());

        $this->assertNoViolation();
    }

    /**
     * Lines of any other product merge, so there is only ever one line of a variant, which Sylius checks
     *
     * @test
     *
     * @dataProvider productsThatAreNotGiftCards
     */
    public function it_leaves_any_other_product_to_sylius(ProductInterface $product): void
    {
        $variant = self::variant('Mug', onHand: 5, product: $product);

        $this->validator->validate(self::order(self::line($variant, 4), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

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
        $variant = self::variant(null, onHand: 5);

        $this->validator->validate(self::order(self::line($variant, 4), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', '')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_has_nothing_to_check_for_a_line_without_a_variant(): void
    {
        $this->validator->validate(self::order(new OrderItem(), new OrderItem()), new GiftCardOrderItemsAvailability());

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
        $variant = self::variant('Gift card', onHand: null);

        $availabilityChecker = $this->prophesize(AvailabilityCheckerInterface::class);
        $availabilityChecker->isStockSufficient($variant, 4)->willReturn(true);
        $availabilityChecker->isStockSufficient($variant, 2)->willReturn(true);
        $availabilityChecker->isStockSufficient($variant, 6)->willReturn(false);
        $this->availabilityChecker = $availabilityChecker->reveal();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate(self::order(self::line($variant, 4), self::line($variant, 2)), new GiftCardOrderItemsAvailability());

        $this->buildViolation('sylius.cart_item.not_available')
            ->setParameter('%itemName%', 'Gift card')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Order(), new NotBlank());
    }

    /** @test */
    public function it_only_validates_an_order(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new OrderItem(), new GiftCardOrderItemsAvailability());
    }

    protected function createValidator(): GiftCardOrderItemsAvailabilityValidator
    {
        return new GiftCardOrderItemsAvailabilityValidator($this->availabilityChecker ?? new AvailabilityChecker());
    }

    private static function order(OrderItem ...$lines): Order
    {
        $order = new Order();
        foreach ($lines as $line) {
            $order->addItem($line);
        }

        return $order;
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
     * A variant of a gift card product of that name unless another product is given, tracked when it is given a
     * stock
     */
    private static function variant(?string $name, ?int $onHand, int $onHold = 0, ?ProductInterface $product = null): ProductVariant
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
