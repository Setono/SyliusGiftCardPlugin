<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Form\DataTransformer\MinorUnitsToLocalizedStringTransformer;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Webmozart\Assert\Assert;

/**
 * Sylius' money field, which shows an amount kept in minor units in major units, refusing an amount whose minor units
 * PHP cannot hold as an integer instead of letting it wrap around to another amount. It renders as Sylius' field does
 *
 * @see MinorUnitsToLocalizedStringTransformer
 *
 * @extends AbstractType<int>
 */
final class MinorUnitsMoneyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scale = $options['scale'];
        Assert::nullOrInteger($scale);

        $grouping = $options['grouping'];
        Assert::nullOrBoolean($grouping);

        $divisor = $options['divisor'];
        Assert::nullOrInteger($divisor);

        // Built from the options exactly as Sylius' MoneyType builds the transformer it replaces
        $builder
            ->resetViewTransformers()
            ->addViewTransformer(new MinorUnitsToLocalizedStringTransformer($scale, $grouping, null, $divisor))
        ;
    }

    public function getParent(): string
    {
        return MoneyType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_minor_units_money';
    }
}
