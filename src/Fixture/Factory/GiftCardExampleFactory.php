<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use function sprintf;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

class GiftCardExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    protected \Faker\Generator $faker;

    protected OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<GiftCardInterface> $giftCardFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        protected GiftCardRepositoryInterface $giftCardRepository,
        protected FactoryInterface $giftCardFactory,
        protected GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        protected ChannelRepositoryInterface $channelRepository,
        protected RepositoryInterface $currencyRepository,
        protected GiftCardBalanceOperatorInterface $balanceOperator,
        protected int $minimumCodeLength = Configuration::MINIMUM_CODE_LENGTH,
    ) {
        $this->faker = \Faker\Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function create(array $options = []): GiftCardInterface
    {
        $options = $this->optionsResolver->resolve($options);

        return $this->createGiftCard($options);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected function createGiftCard(array $options): GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->giftCardRepository->findOneBy(['code' => $options['code']]);
        $giftCard ??= $this->giftCardFactory->createNew();

        /** @var ChannelInterface $channel */
        $channel = $options['channel'];

        // The currency the fixture names, if it names one, has been checked to be this one
        $currency = $channel->getBaseCurrency();
        Assert::notNull($currency);

        /** @var GiftCardDeliveryType $deliveryType */
        $deliveryType = $options['deliveryType'];

        $code = $options['code'];
        Assert::string($code);
        $giftCard->setCode($code);

        $giftCard->setChannel($channel);
        $giftCard->setCurrencyCode((string) $currency->getCode());

        $amount = $options['amount'];
        if (null !== $amount) {
            Assert::integer($amount);
            $giftCard->setInitialAmount($amount);
            $giftCard->setAmount($amount);
        }

        $giftCard->setDeliveryType($deliveryType);
        $giftCard->setEnabled((bool) $options['enabled']);

        // Seeded cards go through the balance operator too, so the demo data does not show cards holding
        // money with an empty ledger behind them
        $this->balanceOperator->issue($giftCard);

        return $giftCard;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', fn (Options $options): string => $this->giftCardCodeGenerator->generate())
            ->setAllowedTypes('code', 'string')
            // Demo data is held to the same minimum as a code typed in the admin: a short code is a guessable one,
            // and demo data has a way of ending up in production
            ->setNormalizer('code', function (Options $options, string $code): string {
                Assert::minLength($code, $this->minimumCodeLength, 'A gift card code must have at least %2$s characters, so it cannot be guessed, got: %s');

                return $code;
            })

            // Resolved without looking at the channel: when the fixture names no channel, the channel is worked out from
            // the currency, so the currency cannot be worked out from the channel as well. Left out, the card is issued
            // in the base currency of its channel
            ->setDefault('currency', null)
            ->setAllowedTypes('currency', ['null', 'string', CurrencyInterface::class])
            ->setNormalizer('currency', function (Options $options, $currency): ?CurrencyInterface {
                if (null === $currency) {
                    return null;
                }

                // A currency handed to the factory is looked up by its code, like a code from a fixture file, so one
                // that is not the managed instance still finds the channels that have it as their base currency
                $code = $currency instanceof CurrencyInterface ? $currency->getCode() : $currency;
                Assert::string($code);

                /** @var CurrencyInterface|null $found */
                $found = $this->currencyRepository->findOneBy(['code' => $code]);
                Assert::notNull($found, sprintf('Currency %s was not found. Gift cards are issued in the base currency of their channel', $code));

                return $found;
            })

            // A fixture naming the currency but no channel gets one of the channels with that base currency, so whether
            // it loads does not depend on which channel happens to be picked
            ->setDefault('channel', function (Options $options): ChannelInterface {
                /** @var CurrencyInterface|null $currency */
                $currency = $options['currency'];
                if (null === $currency) {
                    /** @var ChannelInterface $channel */
                    $channel = LazyOption::randomOne($this->channelRepository)($options);

                    return $channel;
                }

                /** @var list<ChannelInterface> $channels */
                $channels = $this->channelRepository->findBy(['baseCurrency' => $currency]);
                Assert::notEmpty($channels, sprintf(
                    'Gift cards are issued in the base currency of their channel, and no channel has %s as its base currency',
                    (string) $currency->getCode(),
                ));

                return $channels[array_rand($channels)];
            })
            ->setAllowedTypes('channel', ['null', 'string', ChannelInterface::class])
            ->setNormalizer('channel', function (Options $options, $channel): ChannelInterface {
                if (is_string($channel)) {
                    $channelCode = $channel;
                    $channel = $this->channelRepository->findOneBy(['code' => $channelCode]);
                    Assert::isInstanceOf($channel, ChannelInterface::class, sprintf('Channel %s was not found', $channelCode));
                }

                Assert::isInstanceOf($channel, ChannelInterface::class);

                // Sylius keeps every order in the base currency of its channel; the other currencies a channel offers
                // only change how amounts are displayed. A card in any other currency could never be redeemed, so the
                // fixture only issues cards in the base currency, like the admin (GiftCardCurrencyIsChannelBaseCurrency)
                /** @var CurrencyInterface|null $currency */
                $currency = $options['currency'];
                if (null === $currency) {
                    return $channel;
                }

                $baseCurrency = $channel->getBaseCurrency();
                Assert::notNull($baseCurrency);

                Assert::same($currency->getCode(), $baseCurrency->getCode(), sprintf(
                    'Gift cards are issued in the channel\'s base currency (%s for channel %s), got: %s',
                    (string) $baseCurrency->getCode(),
                    (string) $channel->getCode(),
                    (string) $currency->getCode(),
                ));

                return $channel;
            })

            ->setDefault('amount', function (Options $options): int {
                /** @var int $amount */
                $amount = $this->faker->randomElement([10, 20, 30, 40, 50, 75, 100, 150, 200, 250, 300, 400, 500]);

                return $amount;
            })
            ->setAllowedTypes('amount', ['float', 'int'])
            ->setNormalizer('amount', fn (Options $options, float $amount): int => (int) round($amount * 100))

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            ->setDefault('deliveryType', GiftCardDeliveryType::Virtual)
            ->setAllowedTypes('deliveryType', ['string', GiftCardDeliveryType::class])
            ->setNormalizer('deliveryType', static function (Options $options, $deliveryType): GiftCardDeliveryType {
                if ($deliveryType instanceof GiftCardDeliveryType) {
                    return $deliveryType;
                }

                Assert::string($deliveryType);

                return GiftCardDeliveryType::from($deliveryType);
            })
        ;
    }
}
