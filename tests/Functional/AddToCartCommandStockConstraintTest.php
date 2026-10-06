<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\CoreBundle\Validator\Constraints\CartItemAvailability;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The stock check Sylius maps on its own add to cart command is mapped on the plugin's command interface, so it holds
 * for whichever command the add to cart form is bound to: the plugin's, a command that extends it, or one an
 * application writes itself, which the README lets it do by implementing the interface. The validator merges the
 * constraints of the interfaces a class implements into the class's own, and those of a parent class, which already
 * implements the interface, only once
 */
final class AddToCartCommandStockConstraintTest extends GiftCardFunctionalTestCase
{
    /**
     * @param \Closure(OrderInterface, OrderItemInterface, GiftCardInformationInterface): AddToCartCommandInterface $createCommand
     *
     * @test
     *
     * @dataProvider commands
     */
    public function it_checks_the_stock_once_whichever_command_the_form_is_bound_to(\Closure $createCommand): void
    {
        $variant = new ProductVariant();
        $variant->setTracked(true);
        $variant->setOnHand(5);

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setName('Mug');
        $product->addVariant($variant);

        // the cart holds 4 of the 5 in stock, and the form adds 2 more
        $cart = new Order();
        $cart->addItem(self::line($variant, 4));

        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($createCommand($cart, self::line($variant, 2), new GiftCardInformation()), null, ['sylius']);

        self::assertSame([[CartItemAvailability::class, 'Mug does not have sufficient stock.']], array_map(
            static fn (ConstraintViolationInterface $violation): array => [get_debug_type($violation->getConstraint()), (string) $violation->getMessage()],
            iterator_to_array($violations, false),
        ));
    }

    /**
     * @return iterable<string, array{\Closure(OrderInterface, OrderItemInterface, GiftCardInformationInterface): AddToCartCommandInterface}>
     */
    public static function commands(): iterable
    {
        yield 'the plugin\'s command' => [
            static fn (OrderInterface $cart, OrderItemInterface $cartItem, GiftCardInformationInterface $giftCardInformation): AddToCartCommandInterface => new AddToCartCommand($cart, $cartItem, $giftCardInformation),
        ];

        yield 'a command that extends the plugin\'s' => [
            static fn (OrderInterface $cart, OrderItemInterface $cartItem, GiftCardInformationInterface $giftCardInformation): AddToCartCommandInterface => new class($cart, $cartItem, $giftCardInformation) extends AddToCartCommand {
            },
        ];

        yield 'an application\'s own command that implements the interface' => [
            static fn (OrderInterface $cart, OrderItemInterface $cartItem, GiftCardInformationInterface $giftCardInformation): AddToCartCommandInterface => new class($cart, $cartItem, $giftCardInformation) implements AddToCartCommandInterface {
                public function __construct(
                    private readonly OrderInterface $cart,
                    private readonly OrderItemInterface $cartItem,
                    private readonly GiftCardInformationInterface $giftCardInformation,
                ) {
                }

                public function getCart(): OrderInterface
                {
                    return $this->cart;
                }

                public function getCartItem(): OrderItemInterface
                {
                    return $this->cartItem;
                }

                public function getGiftCardInformation(): GiftCardInformationInterface
                {
                    return $this->giftCardInformation;
                }
            },
        ];
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
}
