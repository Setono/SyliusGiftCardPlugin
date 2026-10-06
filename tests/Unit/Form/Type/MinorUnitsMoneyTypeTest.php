<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Setono\SyliusGiftCardPlugin\Form\Type\MinorUnitsMoneyType;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * The plugin's money field is Sylius' with a conversion that cannot wrap around, so it has to behave like Sylius'
 * field in everything else: the same minor units, the same currency for the template
 */
final class MinorUnitsMoneyTypeTest extends TypeTestCase
{
    /** @test */
    public function it_maps_a_typed_amount_to_minor_units_like_sylius_money_field(): void
    {
        foreach ([MoneyType::class, MinorUnitsMoneyType::class] as $type) {
            $form = $this->factory->create($type, null, ['currency' => 'DKK']);
            $form->submit('19.99');

            self::assertTrue($form->isSynchronized(), $type);
            self::assertSame(1999, $form->getData(), $type);
        }
    }

    /**
     * The minor units of this amount are beyond PHP's integer range. Sylius' field casts them, which PHP 8.4 wraps
     * around to 446464; this one cannot read the amount, which the form reports with the money field's invalid message
     *
     * @test
     */
    public function it_cannot_read_an_amount_whose_minor_units_php_cannot_hold(): void
    {
        $form = $this->factory->create(MinorUnitsMoneyType::class, null, ['currency' => 'DKK']);
        $form->submit('184467440737095560');

        self::assertFalse($form->isSynchronized());
        self::assertNull($form->getData());
    }

    /** @test */
    public function it_shows_the_amount_in_major_units_with_the_currency_for_the_template(): void
    {
        $vars = $this->factory->create(MinorUnitsMoneyType::class, 2147483647, ['currency' => 'DKK'])->createView()->vars;
        self::assertIsArray($vars);

        self::assertSame('21474836.47', $vars['value']);
        self::assertSame('DKK', $vars['currency']);
        self::assertIsArray($vars['block_prefixes']);
        self::assertContains('sylius_money', $vars['block_prefixes'], 'it renders like Sylius\' money field');
    }
}
