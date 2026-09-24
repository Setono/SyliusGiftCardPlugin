<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;

/**
 * The customer autocomplete on the admin gift card form searches with this query, which applications add to their
 * customer repository by using the trait, as the test application does
 */
final class CustomerRepositoryTraitTest extends GiftCardFunctionalTestCase
{
    private CustomerRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CustomerRepositoryInterface $repository */
        $repository = self::getContainer()->get('sylius.repository.customer');
        $this->repository = $repository;
    }

    /**
     * The admin types whatever part of the address they remember, so the phrase may match anywhere in it
     *
     * @test
     */
    public function it_finds_customers_whose_email_contains_the_phrase(): void
    {
        $this->createCustomers('john.doe@example.com', 'jane.doe@example.org', 'johnny@example.net');

        self::assertEqualsCanonicalizing(['john.doe@example.com', 'johnny@example.net'], $this->search('john'));
        self::assertEqualsCanonicalizing(['john.doe@example.com', 'jane.doe@example.org'], $this->search('.doe@'));
        self::assertSame(['jane.doe@example.org'], $this->search('example.org'));
        self::assertSame([], $this->search('nobody'));
    }

    /**
     * An autocomplete only shows a handful of suggestions, so the query never loads the whole customer table
     *
     * @test
     */
    public function it_limits_the_number_of_customers_it_finds(): void
    {
        $emails = [];
        for ($i = 1; $i <= 12; ++$i) {
            $emails[] = sprintf('customer%02d@example.com', $i);
        }
        $this->createCustomers(...$emails);

        self::assertCount(10, $this->search('customer'), 'ten customers by default');
        self::assertCount(3, $this->search('customer', 3));
    }

    /**
     * @return list<string>
     */
    private function search(string $phrase, ?int $limit = null): array
    {
        $customers = null === $limit
            ? $this->repository->findByEmailPartForGiftCard($phrase)
            : $this->repository->findByEmailPartForGiftCard($phrase, $limit);

        return array_values(array_map(
            static fn (CustomerInterface $customer): string => (string) $customer->getEmail(),
            $customers,
        ));
    }

    private function createCustomers(string ...$emails): void
    {
        foreach ($emails as $email) {
            $customer = new Customer();
            $customer->setEmail($email);
            $this->manager->persist($customer);
        }

        $this->manager->flush();
        $this->manager->clear();
    }
}
