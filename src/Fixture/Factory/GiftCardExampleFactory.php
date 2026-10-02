<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

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

        /** @var CurrencyInterface $currency */
        $currency = $options['currency'];

        /** @var ChannelInterface $channel */
        $channel = $options['channel'];

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

            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['null', 'string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('currency', function (Options $options): CurrencyInterface {
                /** @var ChannelInterface|mixed $channel */
                $channel = $options['channel'];
                Assert::isInstanceOf($channel, ChannelInterface::class);

                $currency = $channel->getBaseCurrency();
                Assert::notNull($currency);

                return $currency;
            })
            ->setAllowedTypes('currency', ['null', 'string', CurrencyInterface::class])
            // Sylius keeps every order in the base currency of its channel; the other currencies a channel offers
            // only change how amounts are displayed. A card in any other currency could never be redeemed, so the
            // fixture only issues cards in the base currency, like the admin (GiftCardCurrencyIsChannelBaseCurrency)
            ->setNormalizer('currency', function (Options $options, $currencyCode): CurrencyInterface {
                if ($currencyCode instanceof CurrencyInterface) {
                    $currency = $currencyCode;
                    $currencyCode = $currency->getCode();
                } else {
                    /** @var CurrencyInterface|null $currency */
                    $currency = $this->currencyRepository->findOneBy(['code' => $currencyCode]);
                }

                /** @var ChannelInterface|mixed $channel */
                $channel = $options['channel'];
                Assert::isInstanceOf($channel, ChannelInterface::class);

                $baseCurrency = $channel->getBaseCurrency();
                Assert::notNull($baseCurrency);

                // The channel is picked at random when the fixture does not name one, so the message names it
                $issuedIn = sprintf(
                    'Gift cards are issued in the channel\'s base currency (%s for channel %s)',
                    (string) $baseCurrency->getCode(),
                    (string) $channel->getCode(),
                );

                Assert::nullOrString($currencyCode);

                Assert::notNull($currency, sprintf('Currency %s was not found. %s', (string) $currencyCode, $issuedIn));

                Assert::same($currency->getCode(), $baseCurrency->getCode(), sprintf(
                    '%s, got: %s',
                    $issuedIn,
                    (string) $currencyCode,
                ));

                return $currency;
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
