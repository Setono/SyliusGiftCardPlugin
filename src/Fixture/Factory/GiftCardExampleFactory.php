<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
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
    /** @var GiftCardRepositoryInterface */
    protected $giftCardRepository;

    /** @var FactoryInterface */
    protected $giftCardFactory;

    /** @var GiftCardCodeGeneratorInterface */
    protected $giftCardCodeGenerator;

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var RepositoryInterface */
    protected $currencyRepository;

    /** @var \Faker\Generator */
    protected $faker;

    /** @var OptionsResolver */
    protected $optionsResolver;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        FactoryInterface $giftCardFactory,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        ChannelRepositoryInterface $channelRepository,
        RepositoryInterface $currencyRepository
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
        $this->channelRepository = $channelRepository;
        $this->currencyRepository = $currencyRepository;

        $this->faker = \Faker\Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): GiftCardInterface
    {
        $options = $this->optionsResolver->resolve($options);

        return $this->createGiftCard($options);
    }

    protected function createGiftCard(array $options): GiftCardInterface
    {
        /** @var GiftCardInterface|null $giftCard */
        $giftCard = $this->giftCardRepository->findOneBy(['code' => $options['code']]);
        if (null === $giftCard) {
            /** @var GiftCardInterface $giftCard */
            $giftCard = $this->giftCardFactory->createNew();
        }

        /** @var CurrencyInterface $currency */
        $currency = $options['currency'];

        $giftCard->setCode($options['code']);
        $giftCard->setChannel($options['channel']);
        $giftCard->setCurrencyCode((string) $currency->getCode());

        if (null !== $options['amount']) {
            $giftCard->setAmount($options['amount']);
        }

        $giftCard->setEnabled($options['enabled']);

        return $giftCard;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('code', function (Options $options): string {
                return $this->giftCardCodeGenerator->generate();
            })

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
                $channelCurrenciesCodes = $channel->getCurrencies()->map(function (CurrencyInterface $currency): string {
                    $currencyCode = $currency->getCode();
                    Assert::notNull($currencyCode);

                    return $currencyCode;
                })->toArray();

                Assert::notNull($currency, sprintf(
                    'Currency %s was not found. Use one of: %s',
                    $currencyCode,
                    implode(', ', $channelCurrenciesCodes)
                ));

                Assert::isInstanceOf($channel, ChannelInterface::class);

                Assert::oneOf($currency, $channel->getCurrencies()->toArray(), sprintf(
                    'Expecting one of %s currencies, got: %s',
                    implode(', ', $channelCurrenciesCodes),
                    $currencyCode
                ));

                return $currency;
            })

            ->setDefault('amount', function (Options $options): int {
                return $this->faker->randomElement([10, 20, 30, 40, 50, 75, 100, 150, 200, 250, 300, 400, 500]);
            })
            ->setAllowedTypes('amount', ['float', 'int'])
            ->setNormalizer('amount', function (Options $options, float $amount): int {
                return (int) round($amount * 100);
            })

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')
        ;
    }
}
