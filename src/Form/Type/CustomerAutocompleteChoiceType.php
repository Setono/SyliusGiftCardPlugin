<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @extends AbstractType<CustomerInterface>
 */
final class CustomerAutocompleteChoiceType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'resource' => 'sylius.customer',
            'choice_name' => 'email',
            'choice_value' => 'email',
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array<string, mixed> $vars */
        $vars = $view->vars;

        $vars['remote_criteria_type'] = 'contains';
        $vars['remote_criteria_name'] = 'phrase';
        $vars['remote_url'] = $this->urlGenerator->generate('setono_sylius_gift_card_admin_ajax_customer_by_email_phrase');
        $vars['load_edit_url'] = $this->urlGenerator->generate('setono_sylius_gift_card_admin_ajax_customer_by_email');

        $view->vars = $vars;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_gift_card_customer_autocomplete_choice';
    }

    public function getParent(): string
    {
        return ResourceAutocompleteChoiceType::class;
    }
}
