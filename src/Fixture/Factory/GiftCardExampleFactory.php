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

                $channelCurrenciesCodes = $channel->getCurrencies()->map(function (CurrencyInterface $currency): string {
                    $currencyCode = $currency->getCode();
                    Assert::notNull($currencyCode);

                    return $currencyCode;
                })->toArray();

                Assert::nullOrString($currencyCode);

                Assert::notNull($currency, sprintf(
                    'Currency %s was not found. Use one of: %s',
                    (string) $currencyCode,
                    implode(', ', $channelCurrenciesCodes),
                ));

                Assert::oneOf($currency, $channel->getCurrencies()->toArray(), sprintf(
                    'Expecting one of %s currencies, got: %s',
                    implode(', ', $channelCurrenciesCodes),
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
