<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Sylius\Component\Core\Model\Customer;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer field of the gift card form is an autocomplete that asks two admin AJAX routes: one searches by a part
 * of the email address while the admin types, the other loads the customer already chosen when a card is edited.
 * Both hand out customer data, so they are for signed in administrators only
 */
final class CustomerAutocompleteRoutesTest extends AdminFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->persistCustomer('alice@example.com');
        $this->persistCustomer('malice@example.org');
        $this->persistCustomer('bob@example.com');
    }

    /** @test */
    public function it_finds_the_customers_whose_email_contains_the_phrase(): void
    {
        $this->logInAsAdministrator();

        $response = $this->request('GET', '/admin/ajax/customer/search', ['phrase' => 'alice']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['alice@example.com', 'malice@example.org'], self::emails($response));
    }

    /** @test */
    public function it_loads_the_customer_with_exactly_the_email(): void
    {
        $this->logInAsAdministrator();

        $response = $this->request('GET', '/admin/ajax/customer/email', ['email' => 'alice@example.com']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['alice@example.com'], self::emails($response));
    }

    /** @test */
    public function it_does_not_search_customers_for_a_visitor_who_is_not_signed_in(): void
    {
        $response = $this->request('GET', '/admin/ajax/customer/search', ['phrase' => 'example']);

        self::assertTrue($response->isRedirect('http://localhost/admin/login'));
        self::assertStringNotContainsString('alice@example.com', (string) $response->getContent());
    }

    /** @test */
    public function it_does_not_load_a_customer_for_a_visitor_who_is_not_signed_in(): void
    {
        $response = $this->request('GET', '/admin/ajax/customer/email', ['email' => 'alice@example.com']);

        self::assertTrue($response->isRedirect('http://localhost/admin/login'));
        self::assertStringNotContainsString('alice@example.com', (string) $response->getContent());
    }

    private function persistCustomer(string $email): void
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setEmailCanonical($email);

        $this->manager->persist($customer);
        $this->manager->flush();
    }

    /**
     * @return list<string> the email addresses in the JSON response, sorted
     */
    private static function emails(Response $response): array
    {
        self::assertJson((string) $response->getContent());

        /** @var list<array{email: string}> $customers */
        $customers = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $emails = array_map(static fn (array $customer): string => $customer['email'], $customers);
        sort($emails);

        return $emails;
    }
}
