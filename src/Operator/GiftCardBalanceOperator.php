<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Exception\InsufficientGiftCardBalanceException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardBalanceOperator implements GiftCardBalanceOperatorInterface
{
    /**
     * @param FactoryInterface<GiftCardTransactionInterface> $transactionFactory
     * @param RepositoryInterface<GiftCardTransactionInterface> $transactionRepository
     */
    public function __construct(
        private readonly FactoryInterface $transactionFactory,
        private readonly RepositoryInterface $transactionRepository,
        private readonly ObjectManager $transactionManager,
    ) {
    }

    public function redeem(
        GiftCardInterface $giftCard,
        int $amount,
        ?OrderInterface $order = null,
        ?PaymentInterface $payment = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if (null !== $idempotencyKey && null !== $this->transactionRepository->findOneBy(['idempotencyKey' => $idempotencyKey])) {
            return;
        }

        if ($amount > $giftCard->getAmount()) {
            throw new InsufficientGiftCardBalanceException($giftCard, $amount);
        }

        $giftCard->setAmount($giftCard->getAmount() - $amount);

        $this->record($giftCard, -$amount, GiftCardTransactionInterface::TYPE_REDEEM, $order, $payment, $idempotencyKey, null);
    }

    public function restore(
        GiftCardInterface $giftCard,
        int $amount,
        ?OrderInterface $order = null,
        ?PaymentInterface $payment = null,
        ?string $idempotencyKey = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if (null !== $idempotencyKey && null !== $this->transactionRepository->findOneBy(['idempotencyKey' => $idempotencyKey])) {
            return;
        }

        $giftCard->setAmount($giftCard->getAmount() + $amount);

        $this->record($giftCard, $amount, GiftCardTransactionInterface::TYPE_RESTORE, $order, $payment, $idempotencyKey, null);
    }

    public function adjust(GiftCardInterface $giftCard, int $delta, string $reason): void
    {
        $newAmount = $giftCard->getAmount() + $delta;
        Assert::greaterThanEq($newAmount, 0, 'A manual gift card adjustment cannot make the balance negative');

        $giftCard->setAmount($newAmount);

        $this->record($giftCard, $delta, GiftCardTransactionInterface::TYPE_MANUAL, null, null, null, $reason);
    }

    private function record(
        GiftCardInterface $giftCard,
        int $amount,
        string $type,
        ?OrderInterface $order,
        ?PaymentInterface $payment,
        ?string $idempotencyKey,
        ?string $reason,
    ): void {
        $transaction = $this->transactionFactory->createNew();
        $transaction->setAmount($amount);
        $transaction->setType($type);
        $transaction->setOrder($order);
        $transaction->setPayment($payment);
        $transaction->setIdempotencyKey($idempotencyKey);
        $transaction->setReason($reason);

        $giftCard->addTransaction($transaction);

        $this->transactionManager->persist($transaction);
    }
}
