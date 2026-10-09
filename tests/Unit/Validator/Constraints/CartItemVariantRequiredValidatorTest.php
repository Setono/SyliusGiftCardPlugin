<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Order\AddToCartCommand;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\CartItemVariantRequired;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\CartItemVariantRequiredValidator;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The add to cart form leaves the line without a variant when a request leaves out the variant choice, and Sylius'
 * stock check, which runs after this, reads the variant without checking it
 *
 * @extends ConstraintValidatorTestCase<CartItemVariantRequiredValidator>
 */
final class CartItemVariantRequiredValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * Reported on the variant field, which sits under the line's field of the add to cart form
     *
     * @test
     */
    public function it_refuses_a_line_without_a_variant(): void
    {
        $this->validator->validate(self::command(new OrderItem()), new CartItemVariantRequired());

        $this->buildViolation('setono_sylius_gift_card.add_to_cart_command.cart_item.variant.required')
            ->atPath('property.path.cartItem.variant')
            ->assertRaised()
        ;
    }

    /** @test */
    public function it_accepts_a_line_with_a_variant(): void
    {
        $variant = new ProductVariant();
        (new Product())->addVariant($variant);

        $item = new OrderItem();
        $item->setVariant($variant);

        $this->validator->validate(self::command($item), new CartItemVariantRequired());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(self::command(new OrderItem()), new NotBlank());
    }

    /** @test */
    public function it_only_validates_an_add_to_cart_command(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new Order(), new CartItemVariantRequired());
    }

    protected function createValidator(): CartItemVariantRequiredValidator
    {
        return new CartItemVariantRequiredValidator();
    }

    private static function command(OrderItem $item): AddToCartCommand
    {
        return new AddToCartCommand(new Order(), $item, new GiftCardInformation(2500));
    }
}
