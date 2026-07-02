<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

final class GiftCardDesignImageType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'setono_sylius_gift_card.form.gift_card_design.image_type',
                'choices' => [
                    'setono_sylius_gift_card.ui.front' => GiftCardDesignImageInterface::TYPE_FRONT,
                    'setono_sylius_gift_card.ui.back' => GiftCardDesignImageInterface::TYPE_BACK,
                ],
            ])
            ->add('file', FileType::class, [
                'label' => 'sylius.form.image.file',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_design_image';
    }

    public function getDefaultType(): string
    {
        return GiftCardDesignImageInterface::TYPE_FRONT;
    }
}
