<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
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
    use ORMTrait;

    /**
     * @param FactoryInterface<GiftCardTransactionInterface> $transactionFactory
     * @param RepositoryInterface<GiftCardTransactionInterface> $transactionRepository
     */
    public function __construct(
        private readonly FactoryInterface $transactionFactory,
        private readonly RepositoryInterface $transactionRepository,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
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

    public function issue(GiftCardInterface $giftCard): void
    {
        $amount = $giftCard->getAmount();
        if ($amount <= 0) {
            return;
        }

        // Keyed off the gift card code, which is unique, so the nullable unique index on the ledger makes
        // issuance impossible to record twice however many times callers ask for it
        $idempotencyKey = self::issuanceIdempotencyKey($giftCard);
        if (null !== $this->transactionRepository->findOneBy(['idempotencyKey' => $idempotencyKey])) {
            return;
        }

        // Deliberately no setAmount(): the card already holds this balance, the ledger is only catching up
        $this->record($giftCard, $amount, GiftCardTransactionInterface::TYPE_ISSUE, null, null, $idempotencyKey, null);
    }

    private static function issuanceIdempotencyKey(GiftCardInterface $giftCard): string
    {
        $code = $giftCard->getCode();
        Assert::stringNotEmpty($code, 'A gift card must have a code before its issuance can be recorded');

        return 'issue-' . $code;
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

        $this->getManager($transaction)->persist($transaction);
    }
}
