<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceCommand;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

final class AdjustGiftCardBalanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currency = $options['currency'];
        Assert::string($currency);

        // The constraints live on AdjustGiftCardBalanceCommand rather than here, so the rules travel with the
        // data instead of with the one form that happens to produce it
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.adjust_balance.amount',
                'currency' => $currency,
                'help' => 'setono_sylius_gift_card.form.adjust_balance.amount_help',
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'setono_sylius_gift_card.form.adjust_balance.reason',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdjustGiftCardBalanceCommand::class,
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
