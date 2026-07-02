<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GiftCardBalanceOperatorTest extends KernelTestCase
{
    private EntityManagerInterface $manager;

    private GiftCardBalanceOperatorInterface $balanceOperator;

    private GiftCardRepositoryInterface $giftCardRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $manager */
        $manager = $container->get('doctrine.orm.entity_manager');
        $this->manager = $manager;

        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = $container->get(GiftCardBalanceOperatorInterface::class);
        $this->balanceOperator = $balanceOperator;

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = $container->get('setono_sylius_gift_card.repository.gift_card');
        $this->giftCardRepository = $giftCardRepository;

        $this->createSchema();
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

    private function getChannel(): ChannelInterface
    {
        $container = self::getContainer();

        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = $container->get('sylius.repository.channel');

        $channel = $channelRepository->findOneBy(['code' => 'TEST_CHANNEL']);
        if ($channel instanceof ChannelInterface) {
            return $channel;
        }

        $currency = new \Sylius\Component\Currency\Model\Currency();
        $currency->setCode('USD');
        $this->manager->persist($currency);

        $locale = new \Sylius\Component\Locale\Model\Locale();
        $locale->setCode('en_US');
        $this->manager->persist($locale);

        /** @var ChannelFactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelFactory->createNamed('Test channel');
        $channel->setCode('TEST_CHANNEL');
        $channel->setBaseCurrency($currency);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->addLocale($locale);

        $this->manager->persist($channel);
        $this->manager->flush();

        return $channel;
    }

    private function createSchema(): void
    {
        $metadata = $this->manager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->manager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
