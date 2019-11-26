<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Doctrine\Common\Persistence\ObjectManager;
use Faker\Factory;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Webmozart\Assert\Assert;

class GiftCardFixture extends AbstractFixture
{
    /** @var GiftCardFactoryInterface */
    private $giftCardFactory;

    /** @var ObjectManager */
    private $giftCardManager;

    /** @var GiftCardCodeGeneratorInterface */
    private $giftCardCodeGenerator;

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    /** @var \Faker\Generator */
    private $faker;

    public function __construct(
        GiftCardFactoryInterface $giftCardFactory,
        ObjectManager $giftCardManager,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        ChannelRepositoryInterface $channelRepository
    ) {
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardManager = $giftCardManager;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
        $this->channelRepository = $channelRepository;
        $this->faker = Factory::create();
    }

    public function load(array $options): void
    {
        for ($i = 0; $i < $options['amount']; ++$i) {
            $channel = $this->getRandomChannel();

            $baseCurrency = $channel->getBaseCurrency();
            Assert::notNull($baseCurrency);

            $currencyCode = $baseCurrency->getCode();

            $giftCard = $this->giftCardFactory->createNew();
            $giftCard->setCode($this->giftCardCodeGenerator->generate());
            $giftCard->setChannel($channel);
            $giftCard->setCurrencyCode($currencyCode);
            $giftCard->setInitialAmount($this->faker->numberBetween(10000, 100000));

            $this->giftCardManager->persist($giftCard);
        }

        $this->giftCardManager->flush();
    }

    public function getName(): string
    {
        return 'setono_gift_card';
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->integerNode('amount')
                    ->isRequired()
                    ->min(1)
                ->end()
        ;
    }

    private function getRandomChannel(): ChannelInterface
    {
        return $this->faker->randomElement($this->channelRepository->findAll());
    }
}
