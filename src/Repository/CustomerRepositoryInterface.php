<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface as BaseCustomerRepositoryInterface;

/**
 * @extends BaseCustomerRepositoryInterface<CustomerInterface>
 */
interface CustomerRepositoryInterface extends BaseCustomerRepositoryInterface
{
    /**
     * @return array<array-key, CustomerInterface>
     */
    public function findByEmailPartForGiftCard(string $email, int $limit = 10): array;
}
