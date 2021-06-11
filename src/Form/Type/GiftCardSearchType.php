<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Controller\Action\SearchGiftCardCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GiftCardSearchType extends AbstractType
{
    private DataTransformerInterface $giftCardToCodeDataTransformer;

    private array $validationGroups;

    public function __construct(DataTransformerInterface $giftCardToCodeDataTransformer, array $validationGroups)
    {
        $this->giftCardToCodeDataTransformer = $giftCardToCodeDataTransformer;
        $this->validationGroups = $validationGroups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('giftCard', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'setono_sylius_gift_card.ui.enter_gift_card_code',
                ],
                'invalid_message' => 'setono_sylius_gift_card.gift_card_search_command.gift_card.does_not_exist',
            ])
        ;

        $builder->get('giftCard')->addModelTransformer($this->giftCardToCodeDataTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchGiftCardCommand::class,
            'validation_groups' => $this->validationGroups,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_gift_card_search';
    }
}
