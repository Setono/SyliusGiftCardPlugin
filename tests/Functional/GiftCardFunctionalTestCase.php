<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class GiftCardFunctionalTestCase extends KernelTestCase
{
    protected EntityManagerInterface $manager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->manager = $manager;

        $this->createSchema();
    }

    protected function getChannel(): ChannelInterface
    {
        $container = self::getContainer();

        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = $container->get('sylius.repository.channel');

        $channel = $channelRepository->findOneBy(['code' => 'TEST_CHANNEL']);
        if ($channel instanceof ChannelInterface) {
            return $channel;
        }

        $currency = new Currency();
        $currency->setCode('USD');
        $this->manager->persist($currency);

        $locale = new Locale();
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

    /**
     * Creates and persists another enabled channel sharing the currency and locale of the test channel
     */
    protected function createChannel(string $code, ?string $hostname = null): ChannelInterface
    {
        $base = $this->getChannel();

        /** @var ChannelFactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = self::getContainer()->get('sylius.factory.channel');
        /** @var ChannelInterface $channel */
        $channel = $channelFactory->createNamed($code);
        $channel->setCode($code);
        $channel->setHostname($hostname);
        $channel->setBaseCurrency($base->getBaseCurrency());
        $channel->setDefaultLocale($base->getDefaultLocale());
        foreach ($base->getCurrencies() as $currency) {
            $channel->addCurrency($currency);
        }
        foreach ($base->getLocales() as $locale) {
            $channel->addLocale($locale);
        }

        $this->manager->persist($channel);
        $this->manager->flush();

        return $channel;
    }

    /**
     * Creates and persists an enabled gift card holding the given amount on the test channel, in the channel's base
     * currency unless another one is given. It is not flushed, so it goes to the database with whatever the test
     * flushes next
     */
    protected function createEnabledGiftCard(string $code, int $amount, ?string $currencyCode = null): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        if (null !== $currencyCode) {
            $giftCard->setCurrencyCode($currencyCode);
        }
        $giftCard->setInitialAmount($amount);
        $giftCard->setAmount($amount);
        $giftCard->enable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    /**
     * Adds one unit of a new product at the given price to the order. The unit price is set directly, and the product
     * is also sold at that price on the test channel, so an order that goes through cart processing keeps it
     */
    protected function addItem(Order $order, string $code, int $unitPrice, bool $giftCard = false): OrderItem
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode($code);
        $product->setName($code);
        $product->setSlug(strtolower($code));
        $product->setGiftCard($giftCard);
        $product->addChannel($this->getChannel());
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode($code . '_VARIANT');
        $variant->setName($code);
        $variant->setProduct($product);
        $variant->setShippingRequired(false);

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode((string) $this->getChannel()->getCode());
        $channelPricing->setPrice($unitPrice);
        $variant->addChannelPricing($channelPricing);

        $this->manager->persist($variant);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice($unitPrice);
        new OrderItemUnit($item);
        $order->addItem($item);

        return $item;
    }

    private function createSchema(): void
    {
        $metadata = array_values($this->manager->getMetadataFactory()->getAllMetadata());
        $schemaTool = new SchemaTool($this->manager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
