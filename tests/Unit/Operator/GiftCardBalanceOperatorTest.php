<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Operator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Exception\InsufficientGiftCardBalanceException;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperator;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
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

    /**
     * Redemption is what links a movement to the order and the payment it paid for, and the idempotency key is what
     * makes a re-fired state machine callback a no-op, so all three end up on the ledger row
     *
     * @test
     */
    public function it_records_the_order_payment_and_idempotency_key_of_a_redemption(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(5000);
        $order = new Order();
        $payment = new Payment();

        $this->createOperator()->redeem($giftCard, 2000, $order, $payment, 'redeem:order:1:gift_card:1');

        $transaction = $this->firstTransaction($giftCard);
        self::assertSame(GiftCardTransactionInterface::TYPE_REDEEM, $transaction->getType());
        self::assertSame($order, $transaction->getOrder());
        self::assertSame($payment, $transaction->getPayment());
        self::assertSame('redeem:order:1:gift_card:1', $transaction->getIdempotencyKey());
        self::assertNull($transaction->getReason());
        self::assertSame($giftCard, $transaction->getGiftCard());
    }

    /** @test */
    public function it_records_the_order_payment_and_idempotency_key_of_a_restoration(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);
        $order = new Order();
        $payment = new Payment();

        $this->createOperator()->restore($giftCard, 500, $order, $payment, 'restore:order:1:gift_card:1');

        $transaction = $this->firstTransaction($giftCard);
        self::assertSame(GiftCardTransactionInterface::TYPE_RESTORE, $transaction->getType());
        self::assertSame($order, $transaction->getOrder());
        self::assertSame($payment, $transaction->getPayment());
        self::assertSame('restore:order:1:gift_card:1', $transaction->getIdempotencyKey());
    }

    /** @test */
    public function it_lets_the_whole_balance_be_redeemed(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(2000);

        $this->createOperator()->redeem($giftCard, 2000);

        self::assertSame(0, $giftCard->getAmount());
        self::assertSame(-2000, $this->firstTransaction($giftCard)->getAmount());
    }

    /**
     * A zero or negative amount is not a movement, and must neither touch the balance nor leave a ledger row behind
     *
     * @test
     *
     * @dataProvider provideNonPositiveAmounts
     */
    public function it_ignores_a_redemption_or_restoration_of_nothing(int $amount): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $operator = $this->createOperator();
        $operator->redeem($giftCard, $amount);
        $operator->restore($giftCard, $amount);

        self::assertSame(1000, $giftCard->getAmount());
        self::assertCount(0, $giftCard->getTransactions());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositiveAmounts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-500];
    }

    /** @test */
    public function it_restores_only_once_per_idempotency_key(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $this->createOperator(existingTransactionForKey: true)->restore($giftCard, 500, null, null, 'restore:order:1:gift_card:1');

        self::assertSame(1000, $giftCard->getAmount());
        self::assertCount(0, $giftCard->getTransactions());
    }

    /** @test */
    public function it_lets_a_manual_adjustment_empty_the_card(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(1000);

        $this->createOperator()->adjust($giftCard, -1000, 'Refunded in cash');

        self::assertSame(0, $giftCard->getAmount());
        self::assertSame(-1000, $this->firstTransaction($giftCard)->getAmount());
    }

    /**
     * Issuance catches the ledger up with a balance the card already holds, keyed off the card's code so it can only
     * ever be recorded once
     *
     * @test
     */
    public function it_records_the_issuance_of_a_card_without_moving_its_balance(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ISSUEME');
        $giftCard->setAmount(5000);

        $this->createOperator()->issue($giftCard);

        self::assertSame(5000, $giftCard->getAmount());

        $transaction = $this->firstTransaction($giftCard);
        self::assertSame(GiftCardTransactionInterface::TYPE_ISSUE, $transaction->getType());
        self::assertSame(5000, $transaction->getAmount());
        self::assertSame('issue-ISSUEME', $transaction->getIdempotencyKey());
        self::assertNull($transaction->getOrder());
        self::assertNull($transaction->getPayment());
        self::assertNull($transaction->getReason());
    }

    /** @test */
    public function it_does_not_record_the_issuance_of_a_card_without_a_balance(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('EMPTY');
        $giftCard->setAmount(0);

        $this->createOperator()->issue($giftCard);

        self::assertCount(0, $giftCard->getTransactions());
    }

    /** @test */
    public function it_does_not_record_an_issuance_that_was_already_recorded(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setCode('ISSUEME');
        $giftCard->setAmount(5000);

        $this->createOperator(existingTransactionForKey: true)->issue($giftCard);

        self::assertCount(0, $giftCard->getTransactions());
    }

    /**
     * The idempotency key of an issuance is derived from the code, so a card without one cannot be issued safely
     *
     * @test
     */
    public function it_refuses_to_record_the_issuance_of_a_card_without_a_code(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setAmount(5000);

        $this->expectException(\InvalidArgumentException::class);

        $this->createOperator()->issue($giftCard);
    }

    /**
     * The operator is called from within state machine callbacks and controllers that own the unit of work, so it
     * persists what it records and leaves flushing to them
     *
     * @test
     */
    public function it_persists_the_ledger_rows_but_never_flushes(): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->persist(Argument::type(GiftCardTransactionInterface::class))->shouldBeCalledTimes(4);
        $manager->flush()->shouldNotBeCalled();

        $giftCard = new GiftCard();
        $giftCard->setCode('ISSUEME');
        $giftCard->setAmount(5000);

        $operator = $this->createOperator(manager: $manager->reveal());
        $operator->issue($giftCard);
        $operator->redeem($giftCard, 1000);
        $operator->restore($giftCard, 500);
        $operator->adjust($giftCard, 250, 'goodwill');

        self::assertSame(4750, $giftCard->getAmount());
    }

    private function firstTransaction(GiftCard $giftCard): GiftCardTransactionInterface
    {
        $transaction = $giftCard->getTransactions()->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);

        return $transaction;
    }

    private function createOperator(bool $existingTransactionForKey = false, ?EntityManagerInterface $manager = null): GiftCardBalanceOperator
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->will(fn (): GiftCardTransaction => new GiftCardTransaction());

        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findOneBy(Argument::any())->willReturn($existingTransactionForKey ? new GiftCardTransaction() : null);

        if (null === $manager) {
            $managerProphecy = $this->prophesize(EntityManagerInterface::class);
            $managerProphecy->persist(Argument::any())->willReturn(null);
            $manager = $managerProphecy->reveal();
        }

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->willReturn($manager);

        return new GiftCardBalanceOperator($factory->reveal(), $repository->reveal(), $managerRegistry->reveal());
    }
}
