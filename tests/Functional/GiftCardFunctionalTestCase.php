<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
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

    private function createSchema(): void
    {
        $metadata = $this->manager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->manager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
