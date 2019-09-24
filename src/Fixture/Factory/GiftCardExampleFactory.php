<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Product\Generator\ProductVariantGeneratorInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

final class GiftCardExampleFactory extends AbstractExampleFactory
{
    /** @var OrderExampleFactory */
    protected $orderExampleFactory;

    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var ProductOptionRepositoryInterface */
    protected $productOptionRepository;

    /** @var FactoryInterface */
    protected $channelPricingFactory;

    /** @var GiftCardFactoryInterface */
    protected $giftCardFactory;

    /** @var GiftCardRepositoryInterface */
    protected $giftCardRepository;

    /** @var GiftCardCodeFactoryInterface */
    protected $giftCardCodeFactory;

    /** @var GiftCardCodeGeneratorInterface */
    protected $giftCardCodeGenerator;

    /** @var GiftCardCodeRepositoryInterface */
    protected $giftCardCodeRepository;

    /** @var ProductVariantGeneratorInterface */
    protected $productVariantGenerator;

    /** @var \Faker\Generator */
    private $faker;

    /** @var OptionsResolver */
    private $optionsResolver;

    public function __construct(
//        OrderExampleFactory $orderExampleFactory,
        ChannelRepositoryInterface $channelRepository,
        ProductRepositoryInterface $productRepository,
        ProductOptionRepositoryInterface $productOptionRepository,
        FactoryInterface $channelPricingFactory,
        GiftCardFactoryInterface $giftCardFactory,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        ProductVariantGeneratorInterface $productVariantGenerator
    ) {
//        $this->orderExampleFactory = $orderExampleFactory;
        $this->channelRepository = $channelRepository;
        $this->productRepository = $productRepository;
        $this->productOptionRepository = $productOptionRepository;
        $this->channelPricingFactory = $channelPricingFactory;
        $this->giftCardFactory = $giftCardFactory;
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->productVariantGenerator = $productVariantGenerator;

        $this->faker = \Faker\Factory::create();
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('product')
            ->setAllowedTypes('product', ['string', ProductInterface::class])
            ->setNormalizer('product', LazyOption::findOneBy($this->productRepository, 'code'))

            ->setDefined('amount_product_option')
            ->setAllowedTypes('amount_product_option', ['null', 'string', ProductOptionInterface::class])
            ->setNormalizer('amount_product_option', LazyOption::findOneBy($this->productOptionRepository, 'code'))

            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', 'array')
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))

            ->setDefined('amount')
            ->setRequired('amount')
            ->setAllowedTypes('amount', ['null', 'numeric'])

            ->setRequired('codes_count')
            ->setAllowedTypes('codes_count', 'numeric')

            ->setRequired('codes_used_count')
            ->setAllowedTypes('codes_used_count', 'numeric')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): GiftCardInterface
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var ProductInterface $product */
        $product = $options['product'];

        /** @var GiftCardInterface $giftCard */
        $giftCard = $this->giftCardFactory->createForProduct($product);

        if (!$product->isSimple()) {
            Assert::isInstanceOf($options['amount_product_option'], ProductOptionInterface::class, sprintf(
                'You should specify valid ProductOption code at amount_product_option option for not simple GiftCard Product %s',
                $product->getCode()
            ));

            $this->productVariantGenerator->generate($product);

            /** @var ProductOptionInterface $amountProductOption */
            $amountProductOption = $options['amount_product_option'];

            /** @var ProductVariantInterface $productVariant */
            foreach ($product->getVariants()->toArray() as $productVariant) {
                /** @var ChannelInterface $channel */
                $channel = $this->faker->randomElement($options['channels']);

                /** @var Collection|ProductOptionValueInterface[] $amountOptionValues */
                $amountOptionValues = $productVariant->getOptionValues()->filter(function (ProductOptionValueInterface $productOptionValue) use ($amountProductOption) {
                    return $productOptionValue->getOption() === $amountProductOption;
                });

                Assert::notEmpty($amountOptionValues, sprintf(
                    "Product option '%s' should have at least one value.",
                    $amountProductOption
                ));

                /** @var ProductOptionValueInterface $randomProductOptionValue */
                $randomProductOptionValue = $this->faker->randomElement($amountOptionValues->toArray());
                $price = (int) ($randomProductOptionValue->getValue() * 100);

                $channelPricing = $productVariant->getChannelPricingForChannel($channel);
                if (!$channelPricing instanceof ChannelPricingInterface) {
                    /** @var ChannelPricingInterface $channelPricing */
                    $channelPricing = $this->channelPricingFactory->createNew();
                    $channelPricing->setProductVariant($productVariant);
                    $channelPricing->setChannelCode($channel->getCode());

                    $productVariant->addChannelPricing($channelPricing);
                }
                $channelPricing->setPrice($price);
            }
            $this->productRepository->add($product);
        }

        $this->giftCardRepository->add($giftCard);

        $this->createGiftCardCodes($giftCard, $options);

        return $giftCard;
    }

    /**
     * @param GiftCardInterface $giftCard
     * @param array $options
     */
    protected function createGiftCardCodes(GiftCardInterface $giftCard, array $options): void
    {
        $codesCount = (int) $options['codes_count'];
        if ($codesCount < 1) {
            return;
        }

        $codesUsedCount = (int) $options['codes_used_count'];

        /** @var ProductInterface $product */
        $giftCardProduct = $options['product'];

        /** @var ChannelInterface $channel */
        $channel = $this->faker->randomElement($options['channels']);

        $currency = $channel->getBaseCurrency();
        if (!$currency instanceof CurrencyInterface) {
            /** @var CurrencyInterface $currency */
            $currency = $this->faker->randomElement(
                $channel->getCurrencies()->toArray()
            );
        }

        do {
            $giftCardCode = $this->giftCardCodeFactory->createForGiftCard($giftCard);

            if ($giftCardProduct->isSimple()) {
                Assert::numeric($options['amount'], sprintf(
                    'You should specify amount for simple GiftCard Product %s',
                    $giftCardProduct->getCode()
                ));

                $giftCardCode->setInitialAmount((int) ($options['amount'] * 100));
                $giftCardCode->setAmount((int) ($options['amount'] * 100));
            } else {
                // Gift card with random option/amount was bought...
                /** @var ProductVariantInterface $randomProductVariant */
                $randomProductVariant = $this->faker->randomElement($giftCardProduct->getVariants()->toArray());

                $channelPricing = $randomProductVariant->getChannelPricingForChannel($channel);
                Assert::isInstanceOf($channelPricing, ChannelPricingInterface::class, sprintf(
                    "Unable to generate GiftCardCode based on ProductVariant %s as it haven't price for channel %s.",
                    $randomProductVariant->getCode(),
                    $channel->getCode()
                ));

                $giftCardCode->setInitialAmount(
                    $channelPricing->getPrice()
                );
                $giftCardCode->setAmount(
                    $channelPricing->getPrice()
                );
            }

            $giftCardCode->setChannel($channel);
            $giftCardCode->setCode(
                $this->giftCardCodeGenerator->generate()
            );
            $giftCardCode->setCurrencyCode(
                $currency->getCode()
            );
            $giftCardCode->setActive(true);

            // Should we use this gift card code (e.g. create order payed by this gift card code)?
            if ($codesUsedCount-- > 0) {
//                // Is it was used fully (no amount remains)
//                if ($this->faker->boolean(25)) {
//                    $usedAmount = $giftCardCode->getInitialAmount();
//                } else {
//                    $usedAmount = (int) rand(1, $giftCardCode->getInitialAmount());
//                }
//                $remainsAmount = $giftCardCode->getInitialAmount() - $usedAmount;
//
//                $giftCardCode->setAmount($remainsAmount);
//
//                // Not active gift card code have zero amount
//                if ($giftCardCode->getAmount() == 0) {
//                    $giftCardCode->setActive(false);
//                }

//                $this->createOrder($giftCardCode, $usedAmount);
            }

            $this->giftCardCodeRepository->add($giftCardCode);
        } while (--$codesCount > 0);
    }

//    protected function createOrder(GiftCardCodeInterface $giftCardCode, int $usedAmount)
//    {
//        $order = $this->orderExampleFactory->create([
//            'channel' => $giftCardCode->getChannel(),
//            'currency' => $giftCardCode->getCurrencyCode(),
//        ]);
//    }
}
