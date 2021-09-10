<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddToCartGiftCardInformationType extends AbstractType
{
    private string $dataClass;

    private array $validationGroups;

    public function __construct(string $dataClass, array $validationGroups)
    {
        $this->dataClass = $dataClass;
        $this->validationGroups = $validationGroups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $form = $event->getForm();
            /** @var ProductInterface|null $product */
            $product = $options['product'];
            if (null === $product || !$product->isGiftCardAmountConfigurable()) {
                return;
            }

            $form->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_gift_card.form.add_to_cart.gift_card_information.amount',
            ]);
        });

        $builder->add('customMessage', TextareaType::class, [
            'required' => false,
            'label' => 'setono_sylius_gift_card.form.add_to_cart.gift_card_information.custom_message',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', $this->dataClass);
        $resolver->setDefault('validation_groups', $this->validationGroups);
        $resolver->setDefault('product', null);
        $resolver->addAllowedTypes('product', ['null', ProductInterface::class]);
    }
}
