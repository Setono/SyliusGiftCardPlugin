<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Operator;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Exception\InsufficientGiftCardBalanceException;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperator;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardBalanceOperatorTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_redeems_and_records_a_transaction(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(5000);

        $operator = $this->createOperator();
        $operator->redeem($giftCard, 2000);

        self::assertSame(3000, $giftCard->getAmount());
        self::assertCount(1, $giftCard->getTransactions());
        self::assertSame(-2000, $this->firstTransaction($giftCard)->getAmount());
    }

    /** @test */
    public function it_restores_and_records_a_transaction(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $operator = $this->createOperator();
        $operator->restore($giftCard, 500);

        self::assertSame(1500, $giftCard->getAmount());
        self::assertSame(500, $this->firstTransaction($giftCard)->getAmount());
    }

    /** @test */
    public function it_throws_when_redeeming_more_than_the_balance(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $this->expectException(InsufficientGiftCardBalanceException::class);

        $this->createOperator()->redeem($giftCard, 2000);
    }

    /** @test */
    public function it_is_idempotent_when_an_idempotency_key_already_exists(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(5000);

        $operator = $this->createOperator(existingTransactionForKey: true);
        $operator->redeem($giftCard, 2000, null, null, 'redeem:order:1:gift_card:1');

        self::assertSame(5000, $giftCard->getAmount());
        self::assertCount(0, $giftCard->getTransactions());
    }

    /** @test */
    public function it_adjusts_the_balance_manually_with_a_reason(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $operator = $this->createOperator();
        $operator->adjust($giftCard, 500, 'goodwill');

        self::assertSame(1500, $giftCard->getAmount());
        $transaction = $this->firstTransaction($giftCard);
        self::assertSame(GiftCardTransactionInterface::TYPE_MANUAL, $transaction->getType());
        self::assertSame('goodwill', $transaction->getReason());
    }

    /** @test */
    public function it_does_not_allow_a_manual_adjustment_to_go_negative(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $this->expectException(\InvalidArgumentException::class);

        $this->createOperator()->adjust($giftCard, -2000, 'mistake');
    }

    private function firstTransaction(GiftCard $giftCard): GiftCardTransactionInterface
    {
        $transaction = $giftCard->getTransactions()->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);

        return $transaction;
    }

    private function createOperator(bool $existingTransactionForKey = false): GiftCardBalanceOperator
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->will(fn (): GiftCardTransaction => new GiftCardTransaction());

        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findOneBy(Argument::any())->willReturn($existingTransactionForKey ? new GiftCardTransaction() : null);

        $manager = $this->prophesize(ObjectManager::class);
        $manager->persist(Argument::any())->willReturn(null);

        return new GiftCardBalanceOperator($factory->reveal(), $repository->reveal(), $manager->reveal());
    }
}
