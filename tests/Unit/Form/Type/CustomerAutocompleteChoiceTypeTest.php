<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Form\Type\CustomerAutocompleteChoiceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The customer field of the gift card form is a Sylius autocomplete that talks to the plugin's admin AJAX routes and
 * identifies customers by their email address, which is what the admin types and what the routes search
 */
final class CustomerAutocompleteChoiceTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RepositoryInterface<CustomerInterface>> */
    private ObjectProphecy $customerRepository;

    /** @test */
    public function it_points_the_autocomplete_at_the_admin_customer_routes(): void
    {
        $vars = self::vars($this->factory->create(CustomerAutocompleteChoiceType::class)->createView());

        // Searched while the admin types a part of an email address
        self::assertSame('/admin/ajax/customer/search', $vars['remote_url']);
        self::assertSame('phrase', $vars['remote_criteria_name']);
        self::assertSame('contains', $vars['remote_criteria_type']);
        // Loads the customer a card already has when it is edited
        self::assertSame('/admin/ajax/customer/email', $vars['load_edit_url']);

        self::assertSame('email', $vars['choice_name']);
        self::assertSame('email', $vars['choice_value']);
    }

    /** @test */
    public function it_maps_the_submitted_email_to_the_customer(): void
    {
        $customer = $this->customer('alice@example.com');
        $this->customerRepository->findOneBy(['email' => 'alice@example.com'])->willReturn($customer);

        $form = $this->factory->create(CustomerAutocompleteChoiceType::class);
        $form->submit('alice@example.com');

        self::assertTrue($form->isSynchronized());
        self::assertSame($customer, $form->getData());
    }

    /**
     * Rendered as a field of a gift card that already has a customer, the way the edit form uses it
     *
     * @test
     */
    public function it_shows_the_email_of_the_customer_it_holds(): void
    {
        $form = $this->factory
            ->createBuilder(FormType::class, ['customer' => $this->customer('alice@example.com')])
            ->add('customer', CustomerAutocompleteChoiceType::class)
            ->getForm()
        ;

        self::assertSame('alice@example.com', self::vars($form->createView()->children['customer'])['value']);
    }

    /** @test */
    public function it_leaves_the_customer_out_when_nothing_is_chosen(): void
    {
        $form = $this->factory->create(CustomerAutocompleteChoiceType::class);
        $form->submit('');

        self::assertTrue($form->isSynchronized());
        self::assertNull($form->getData());
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        /** @var ObjectProphecy<RepositoryInterface<CustomerInterface>> $customerRepository */
        $customerRepository = $this->prophesize(RepositoryInterface::class);
        $this->customerRepository = $customerRepository;
        $this->customerRepository->getClassName()->willReturn(Customer::class);

        $repositoryRegistry = $this->prophesize(ServiceRegistryInterface::class);
        $repositoryRegistry->get('sylius.customer')->willReturn($this->customerRepository->reveal());

        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_gift_card_admin_ajax_customer_by_email_phrase')->willReturn('/admin/ajax/customer/search');
        $urlGenerator->generate('setono_sylius_gift_card_admin_ajax_customer_by_email')->willReturn('/admin/ajax/customer/email');

        return [new PreloadedExtension([
            new CustomerAutocompleteChoiceType($urlGenerator->reveal()),
            new ResourceAutocompleteChoiceType($repositoryRegistry->reveal()),
        ], [])];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function vars(FormView $view): array
    {
        self::assertIsArray($view->vars);

        return $view->vars;
    }

    private function customer(string $email): CustomerInterface
    {
        $customer = new Customer();
        $customer->setEmail($email);

        return $customer;
    }
}
