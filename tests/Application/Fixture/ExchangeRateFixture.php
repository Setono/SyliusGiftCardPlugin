<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Application\Fixture;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Webmozart\Assert\Assert;

/**
 * Sylius has no fixture for the exchange rates a channel showing its prices in another currency than its base currency
 * converts them with, and the end to end suite's channel shows its prices in euros as well as dollars, so that a gift
 * card can be bought and redeemed in a display currency (#488)
 */
final class ExchangeRateFixture extends AbstractFixture
{
    use ORMTrait;

    /**
     * @param FactoryInterface<ExchangeRateInterface> $exchangeRateFactory
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        private readonly FactoryInterface $exchangeRateFactory,
        ManagerRegistry $managerRegistry,
        private readonly RepositoryInterface $currencyRepository,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getName(): string
    {
        return 'e2e_exchange_rate';
    }

    /**
     * @param array{rates: list<array{source: string, target: string, ratio: float}>} $options
     */
    public function load(array $options): void
    {
        foreach ($options['rates'] as $rate) {
            $exchangeRate = $this->exchangeRateFactory->createNew();
            $exchangeRate->setSourceCurrency($this->currency($rate['source']));
            $exchangeRate->setTargetCurrency($this->currency($rate['target']));
            $exchangeRate->setRatio($rate['ratio']);

            $manager = $this->getManager($exchangeRate);
            $manager->persist($exchangeRate);
            $manager->flush();
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('rates')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('source')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('target')->isRequired()->cannotBeEmpty()->end()
                            ->floatNode('ratio')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function currency(string $code): CurrencyInterface
    {
        $currency = $this->currencyRepository->findOneBy(['code' => $code]);
        Assert::isInstanceOf($currency, CurrencyInterface::class, sprintf('Currency %s was not found', $code));

        return $currency;
    }
}
