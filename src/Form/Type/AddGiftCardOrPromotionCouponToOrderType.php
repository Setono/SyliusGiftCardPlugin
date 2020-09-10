<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardOrPromotionCouponToOrderCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddGiftCardOrPromotionCouponToOrderType extends AbstractType
{
    /** @var DataTransformerInterface */
    private $giftCardToCodeDataTransformer;

    /** @var DataTransformerInterface */
    private $promotionCouponToCodeDataTransformer;

    /** @var array */
    private $validationGroups;

    public function __construct(
        DataTransformerInterface $giftCardToCodeDataTransformer,
        DataTransformerInterface $promotionCouponToCodeDataTransformer,
        array $validationGroups
    ) {
        $this->giftCardToCodeDataTransformer = $giftCardToCodeDataTransformer;
        $this->promotionCouponToCodeDataTransformer = $promotionCouponToCodeDataTransformer;
        $this->validationGroups = $validationGroups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('giftCardOrPromotion', TextType::class, [
                'label' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'setono_sylius_gift_card.ui.enter_gift_card_code',
                ],
                'invalid_message' => 'setono_sylius_gift_card.add_gift_card_to_order_command.gift_card_or_coupon.does_not_exist',
            ])
            ->add('giftCard', HiddenType::class, [
                'label' => false,
                'required' => false,
                'invalid_message' => 'setono_sylius_gift_card.add_gift_card_to_order_command.gift_card_or_coupon.does_not_exist',
            ])
            ->add('promotionCoupon', HiddenType::class, [
                'label' => false,
                'required' => false,
                'invalid_message' => 'setono_sylius_gift_card.add_gift_card_to_order_command.gift_card_or_coupon.does_not_exist',
            ])
        ;

        $builder->get('giftCard')->addModelTransformer($this->giftCardToCodeDataTransformer);
        $builder->get('promotionCoupon')->addModelTransformer($this->promotionCouponToCodeDataTransformer);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            $data['giftCard'] = $data['giftCardOrPromotion'];
            $data['promotionCoupon'] = $data['giftCardOrPromotion'];

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddGiftCardOrPromotionCouponToOrderCommand::class,
            'validation_groups' => $this->validationGroups,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_add_gift_card_to_order';
    }
}
