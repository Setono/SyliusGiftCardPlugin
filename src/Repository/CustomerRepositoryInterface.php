<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Repository;

use Sylius\Component\Core\Repository\CustomerRepositoryInterface as BaseCustomerRepositoryInterface;

interface CustomerRepositoryInterface extends BaseCustomerRepositoryInterface
{
    public function findByEmailPartForGiftCard(string $email, int $limit = 10): array;
}
