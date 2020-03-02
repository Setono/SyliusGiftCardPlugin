<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\CoreBundle\Form\Type\ImageType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GiftCardConfigurationImageType extends ImageType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        if ($options['remove_type']) {
            $builder->remove('type');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('remove_type', false);
        $resolver->setAllowedTypes('remove_type', ['null', 'bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_configuration_image';
    }
}
