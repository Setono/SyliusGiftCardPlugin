<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Doctrine\ORM\EntityRepository;
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
    public function __construct(
        private readonly string $customerClass,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->customerClass,
            'choice_label' => 'email',
            'query_builder' => static function (EntityRepository $repository) {
                return $repository->createQueryBuilder('c');
            },
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_customer_autocomplete_choice';
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
