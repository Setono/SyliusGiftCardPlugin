<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Webmozart\Assert\Assert;

final class AdjustGiftCardBalanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currency = $options['currency'];
        Assert::string($currency);

        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.adjust_balance.amount',
                'currency' => $currency,
                'help' => 'setono_sylius_gift_card.form.adjust_balance.amount_help',
                'constraints' => [
                    new NotEqualTo(['value' => 0, 'groups' => ['setono_sylius_gift_card']]),
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'setono_sylius_gift_card.form.adjust_balance.reason',
                'constraints' => [
                    new NotBlank(['groups' => ['setono_sylius_gift_card']]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'currency' => 'USD',
            'validation_groups' => ['setono_sylius_gift_card'],
        ]);
        $resolver->setAllowedTypes('currency', 'string');
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_adjust_balance';
    }
}
