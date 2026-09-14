<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardToOrderCommand;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AddGiftCardToOrderCommand>
 */
final class AddGiftCardToOrderType extends AbstractType
{
    /**
     * @param DataTransformerInterface<GiftCardInterface, string> $giftCardToCodeDataTransformer
     * @param list<string> $validationGroups
     */
    public function __construct(private readonly DataTransformerInterface $giftCardToCodeDataTransformer, private readonly array $validationGroups)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('giftCard', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'setono_sylius_gift_card.ui.enter_gift_card_code',
                ],
                // Deliberately the same message the GiftCardIsApplicable constraint adds: a customer who
                // enters a code that does not exist must not be able to tell that apart from a code that
                // exists but cannot be used, or the form becomes an oracle for guessing codes
                'invalid_message' => 'setono_sylius_gift_card.gift_card.could_not_be_applied',
            ])
        ;

        $builder->get('giftCard')->addModelTransformer($this->giftCardToCodeDataTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddGiftCardToOrderCommand::class,
            'validation_groups' => $this->validationGroups,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_add_gift_card_to_order';
    }
}
