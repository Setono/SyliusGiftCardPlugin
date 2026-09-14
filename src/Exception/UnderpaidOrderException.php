<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Exception;

use RuntimeException;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;

/**
 * Thrown when an order is about to be placed with payments adding up to less than its total, which happens when a
 * gift card applied to the cart no longer covers what the gateway payment was sized (or the payment step skipped) for
 */
final class UnderpaidOrderException extends RuntimeException implements ExceptionInterface
{
    public function __construct(
        private readonly OrderInterface $order,
        private readonly int $paidAmount,
    ) {
        parent::__construct(sprintf(
            'The payments on order %s add up to %d, but its total is %d: the gift cards applied to it no longer cover what they did',
            (string) $order->getId(),
            $paidAmount,
            $order->getTotal(),
        ));
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }
}
