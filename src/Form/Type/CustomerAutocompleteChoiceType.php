<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField(
    alias: 'setono_sylius_gift_card_customer',
    route: 'sylius_admin_entity_autocomplete',
)]
final class CustomerAutocompleteChoiceType extends AbstractType
{
    public function __construct(private readonly string $customerClass)
    {
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->customerClass,
            'choice_label' => 'email',
            'searchable_fields' => ['email', 'firstName', 'lastName'],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_customer_autocomplete_choice';
    }

    #[\Override]
    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
