<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardBalanceOperatorTest extends GiftCardFunctionalTestCase
{
    private GiftCardBalanceOperatorInterface $balanceOperator;

    private GiftCardRepositoryInterface $giftCardRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $container = self::getContainer();

        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = $container->get(GiftCardBalanceOperatorInterface::class);
        $this->balanceOperator = $balanceOperator;

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = $container->get('setono_sylius_gift_card.repository.gift_card');
        $this->giftCardRepository = $giftCardRepository;
    }

    /**
     * The ledger is supposed to account for the whole balance, so the opening balance has to be a row too —
     * otherwise the transactions of a freshly issued card sum to nothing while it demonstrably holds money
     *
     * @test
     */
    public function it_records_the_opening_balance_without_moving_it(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->balanceOperator->issue($giftCard);
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->giftCardRepository->findOneByCode('FUNCTIONALTEST01');
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);
        self::assertSame(5000, $reloaded->getAmount(), 'issuing must not move the balance, only record it');

        $transactions = $reloaded->getTransactions();
        self::assertCount(1, $transactions);

        $transaction = $transactions->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);
        self::assertSame(GiftCardTransactionInterface::TYPE_ISSUE, $transaction->getType());
        self::assertSame(5000, $transaction->getAmount());
    }

    /** @test */
    public function it_only_records_issuance_once_per_gift_card(): void
    {
        $giftCard = $this->createGiftCard(5000);

        // Callers should not have to track whether issuance was already recorded
        $this->balanceOperator->issue($giftCard);
        $this->manager->flush();
        $this->balanceOperator->issue($giftCard);
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->giftCardRepository->findOneByCode('FUNCTIONALTEST01');
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);
        self::assertCount(1, $reloaded->getTransactions());
    }

    /**
     * The ledger should reconcile: what the card holds is the sum of what the ledger says happened to it
     *
     * @test
     */
    public function the_ledger_sums_to_the_balance(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->balanceOperator->issue($giftCard);
        $this->balanceOperator->redeem($giftCard, 1500, null, null, 'redeem:sum:1');
        $this->balanceOperator->adjust($giftCard, 250, 'goodwill');
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->giftCardRepository->findOneByCode('FUNCTIONALTEST01');
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);

        $sum = 0;
        foreach ($reloaded->getTransactions() as $transaction) {
            $sum += $transaction->getAmount();
        }

        self::assertSame($reloaded->getAmount(), $sum);
        self::assertSame(3750, $sum);
    }

    /** @test */
    public function it_redeems_the_balance_and_persists_a_ledger_row(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->balanceOperator->redeem($giftCard, 2000, null, null, 'redeem:test:1');
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->giftCardRepository->findOneByCode('FUNCTIONALTEST01');
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);
        self::assertSame(3000, $reloaded->getAmount());
        self::assertCount(1, $reloaded->getTransactions());
    }

    /** @test */
    public function it_is_idempotent_across_flushes(): void
    {
        $giftCard = $this->createGiftCard(5000);

        $this->balanceOperator->redeem($giftCard, 2000, null, null, 'redeem:test:idempotent');
        $this->manager->flush();

        // a re-fired callback with the same key must not decrement again
        $this->balanceOperator->redeem($giftCard, 2000, null, null, 'redeem:test:idempotent');
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->giftCardRepository->findOneByCode('FUNCTIONALTEST01');
        self::assertInstanceOf(GiftCardInterface::class, $reloaded);
        self::assertSame(3000, $reloaded->getAmount());
        self::assertCount(1, $reloaded->getTransactions());
    }

    /** @test */
    public function it_aggregates_the_outstanding_balance(): void
    {
        $this->createGiftCard(5000);

        $balances = $this->giftCardRepository->findBalance(new \DateTimeImmutable());

        self::assertNotEmpty($balances);
        self::assertSame('USD', $balances[0]['currencyCode']);
        self::assertSame(5000, $balances[0]['amount']);
    }

    private function createGiftCard(int $amount): GiftCardInterface
    {
        $container = self::getContainer();

        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = $container->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('FUNCTIONALTEST01');
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }
}
