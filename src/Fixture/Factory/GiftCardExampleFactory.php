<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
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
        protected GiftCardCurrencyProviderInterface $currencyProvider,
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

        // The currency the fixture names has been checked to be one the channel offers. Left out, it is the base currency
        /** @var CurrencyInterface|null $currency */
        $currency = $options['currency'] ?? $channel->getBaseCurrency();
        Assert::notNull($currency);

        /** @var GiftCardDeliveryType $deliveryType */
        $deliveryType = $options['delivery_type'];

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

    protected function offers(ChannelInterface $channel, CurrencyInterface $currency): bool
    {
        return in_array($currency->getCode(), $this->currencyProvider->getCurrencyCodes($channel), true);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', fn (Options $options): string => $this->giftCardCodeGenerator->generate())
            ->setAllowedTypes('code', 'string')
            // Demo data is held to the same minimum as a code typed in the admin: a short code is a guessable one,
            // and demo data has a way of ending up in production
            ->setNormalizer('code', function (Options $options, string $code): string {
                // The configuration refuses a minimum_code_length outside these bounds, but cannot see the value of
                // one taken from an environment variable, which is only known at runtime
                Assert::range($this->minimumCodeLength, Configuration::MINIMUM_CODE_LENGTH, Configuration::MAXIMUM_CODE_LENGTH, 'The minimum_code_length (%s) must be between %2$s and %3$s');
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
                // that is not the managed instance is the same currency as the one the channels offer
                $code = $currency instanceof CurrencyInterface ? $currency->getCode() : $currency;
                Assert::string($code);

                /** @var CurrencyInterface|null $found */
                $found = $this->currencyRepository->findOneBy(['code' => $code]);
                Assert::notNull($found, sprintf('Currency %s was not found', $code));

                return $found;
            })

            // A fixture naming the currency but no channel gets one of the channels with that base currency, else one of
            // the channels offering it besides, so whether it loads does not depend on which channel happens to be picked
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
                if ([] === $channels) {
                    /** @var list<ChannelInterface> $channels */
                    $channels = array_values(array_filter(
                        $this->channelRepository->findAll(),
                        fn (ChannelInterface $channel): bool => $this->offers($channel, $currency),
                    ));
                }

                Assert::notEmpty($channels, sprintf(
                    'Gift cards are issued in a currency their channel offers, and no channel offers %s',
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

                // A card is held in a currency its channel offers, and pays converted into the order's currency, which is
                // the channel's base currency (#488). A card in a currency the channel does not offer could never be
                // redeemed, so the fixture refuses it, like the admin (GiftCardCurrencyIsOfferedByChannel)
                /** @var CurrencyInterface|null $currency */
                $currency = $options['currency'];
                if (null === $currency) {
                    return $channel;
                }

                Assert::true($this->offers($channel, $currency), sprintf(
                    'Gift cards are issued in a currency their channel offers (%s for channel %s), got: %s',
                    implode(', ', $this->currencyProvider->getCurrencyCodes($channel)),
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
            ->setNormalizer('amount', static function (Options $options, float $amount): int {
                $minorUnits = round($amount * 100);

                // The balance is mapped as Doctrine's integer type, a signed 32-bit integer in its portable type system,
                // which the database holds a card to. It is checked before the cast, which would wrap a larger float
                // around to an arbitrary amount instead
                Assert::range($minorUnits, -2147483648, 2147483647, sprintf(
                    'A gift card amount has to fit the integer column its balance is kept in, from -21474836.48 to 21474836.47, got: %s',
                    $amount,
                ));

                return (int) $minorUnits;
            })

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            // Named like the fixture's node, because the fixture hands each entry to this factory as it is. A fixture
            // file gives the value, an application building on the factory may give the case
            ->setDefault('delivery_type', GiftCardDeliveryType::Virtual)
            ->setAllowedTypes('delivery_type', ['string', GiftCardDeliveryType::class])
            ->setNormalizer('delivery_type', static function (Options $options, $deliveryType): GiftCardDeliveryType {
                if ($deliveryType instanceof GiftCardDeliveryType) {
                    return $deliveryType;
                }

                Assert::string($deliveryType);

                return GiftCardDeliveryType::from($deliveryType);
            })
        ;
    }
}
