<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\LocaleBundle\Form\Type\LocaleChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\FormBuilderInterface;

final class GiftCardChannelConfigurationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('channel', ChannelChoiceType::class, [
            'label' => 'setono_sylius_gift_card.form.channel_configuration.channel',
            'expanded' => false,
            'multiple' => false,
        ]);
        $builder->add('locale', LocaleChoiceType::class, [
            'label' => 'setono_sylius_gift_card.form.channel_configuration.locale',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_channel_configuration';
    }
}
