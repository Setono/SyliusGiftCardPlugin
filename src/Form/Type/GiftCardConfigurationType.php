<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GiftCardConfigurationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card_configuration.code',
        ]);
        $builder->add('enabled', CheckboxType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card_configuration.enabled',
            'required' => false,
        ]);
        $builder->add('default', CheckboxType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card_configuration.default',
            'required' => false,
        ]);
        $builder->add('backgroundImage', GiftCardConfigurationImageType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card_configuration.background_image',
            'required' => false,
            'remove_type' => true,
        ]);
        $builder->add('channelConfigurations', CollectionType::class, [
            'label' => 'setono_sylius_gift_card.form.gift_card_configuration.channel_configurations',
            'entry_type' => GiftCardChannelConfigurationType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_configuration';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('validation_groups', function (FormInterface $form): array {
            $validationGroups = $this->validationGroups;
            $data = $form->getData();
            if (null === $data->getId()) {
                $validationGroups[] = 'setono_sylius_gift_card_create';
            }

            return $validationGroups;
        });
    }
}
