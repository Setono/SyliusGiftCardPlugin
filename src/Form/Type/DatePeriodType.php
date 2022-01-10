<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class DatePeriodType extends AbstractType
{
    private DatePeriodUnitProviderInterface $datePeriodUnitProvider;

    public function __construct(DatePeriodUnitProviderInterface $datePeriodUnitProvider)
    {
        $this->datePeriodUnitProvider = $datePeriodUnitProvider;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', TextType::class, [
            'label' => 'setono_sylius_gift_card.form.date_period.value',
        ]);
        $builder->add('unit', ChoiceType::class, [
            'label' => 'setono_sylius_gift_card.form.date_period.unit',
            'choices' => $this->datePeriodUnitProvider->getPeriodUnits(),
            'choice_label' => function (string $choice): string {
                return \sprintf('setono_sylius_gift_card.form.date_period.unit_%s', $choice);
            },
        ]);
    }
}
