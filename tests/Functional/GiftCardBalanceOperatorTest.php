<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
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
