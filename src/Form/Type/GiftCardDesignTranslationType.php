<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class GiftCardDesignTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // NotBlank lives on the GiftCardDesignTranslation entity (validation/GiftCardDesignTranslation.xml) rather than
        // here: a form-level constraint would be applied to every rendered locale, forcing the name to be filled in all
        // languages. On the entity it is only validated for the translations kept by ResourceTranslationsType (the
        // default locale), so only the default locale name is required.
        $builder
            ->add('name', TextType::class, [
                'label' => 'sylius.ui.name',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_design_translation';
    }
}
