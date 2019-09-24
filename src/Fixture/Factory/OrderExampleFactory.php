<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture\Factory;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardCodeRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Checker\OrderPaymentMethodSelectionRequirementCheckerInterface;
use Sylius\Component\Core\Checker\OrderShippingMethodSelectionRequirementCheckerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Product\Generator\ProductVariantGeneratorInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Repository\ProductOptionRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

final class OrderExampleFactory extends AbstractExampleFactory
{
    /** @var FactoryInterface */
    private $orderFactory;

    /** @var FactoryInterface */
    private $orderItemFactory;

    /** @var OrderItemQuantityModifierInterface */
    private $orderItemQuantityModifier;

    /** @var ObjectManager */
    private $orderManager;

    /** @var RepositoryInterface */
    private $channelRepository;

    /** @var RepositoryInterface */
    private $localeRepository;

    /** @var RepositoryInterface */
    private $currencyRepository;

    /** @var RepositoryInterface */
    private $customerRepository;

    /** @var GiftCardCodeRepositoryInterface */
    private $giftCardCodeRepository;

    /** @var RepositoryInterface */
    private $productRepository;

    /** @var RepositoryInterface */
    private $productVariantRepository;

    /** @var RepositoryInterface */
    private $countryRepository;

    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var ShippingMethodRepositoryInterface */
    private $shippingMethodRepository;

    /** @var FactoryInterface */
    private $addressFactory;

    /** @var StateMachineFactoryInterface */
    private $stateMachineFactory;

    /** @var OrderShippingMethodSelectionRequirementCheckerInterface */
    private $orderShippingMethodSelectionRequirementChecker;

    /** @var OrderPaymentMethodSelectionRequirementCheckerInterface */
    private $orderPaymentMethodSelectionRequirementChecker;

    /** @var \Faker\Generator */
    private $faker;

    public function __construct(
        FactoryInterface $orderFactory,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        ObjectManager $orderManager,
        RepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository,
        RepositoryInterface $currencyRepository,
        RepositoryInterface $customerRepository,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        RepositoryInterface $productRepository,
        RepositoryInterface $productVariantRepository,
        RepositoryInterface $countryRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        ShippingMethodRepositoryInterface $shippingMethodRepository,
        FactoryInterface $addressFactory,
        StateMachineFactoryInterface $stateMachineFactory,
        OrderShippingMethodSelectionRequirementCheckerInterface $orderShippingMethodSelectionRequirementChecker,
        OrderPaymentMethodSelectionRequirementCheckerInterface $orderPaymentMethodSelectionRequirementChecker
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderItemQuantityModifier = $orderItemQuantityModifier;
        $this->orderManager = $orderManager;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->currencyRepository = $currencyRepository;
        $this->customerRepository = $customerRepository;
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->countryRepository = $countryRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->addressFactory = $addressFactory;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderShippingMethodSelectionRequirementChecker = $orderShippingMethodSelectionRequirementChecker;
        $this->orderPaymentMethodSelectionRequirementChecker = $orderPaymentMethodSelectionRequirementChecker;

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
            ->setDefault('customer', LazyOption::randomOne($this->customerRepository))
            ->setAllowedTypes('customer', ['string', CustomerInterface::class])
            ->setNormalizer('customer', LazyOption::findOneBy($this->customerRepository, 'email'))

            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('locale', function (Options $options): LocaleInterface {
                /** @var ChannelInterface $channel */
                $channel = $options['channel'];

                $defaultLocale = $channel->getDefaultLocale();
                if ($defaultLocale instanceof LocaleInterface) {
                    return $defaultLocale;
                }

                return $this->faker->randomElement($channel->getLocales()->toArray());
            })
            ->setAllowedTypes('locale', ['string', LocaleInterface::class])
            ->setNormalizer('locale', LazyOption::findOneBy($this->localeRepository, 'code'))

            ->setDefault('currency', function (Options $options): CurrencyInterface {
                /** @var ChannelInterface $channel */
                $channel = $options['channel'];

                $baseCurrency = $channel->getBaseCurrency();
                if ($baseCurrency instanceof CurrencyInterface) {
                    return $baseCurrency;
                }

                return $this->faker->randomElement($channel->getCurrencies()->toArray());
            })
            ->setAllowedTypes('currency', ['string', CurrencyInterface::class])
            ->setNormalizer('currency', LazyOption::findOneBy($this->currencyRepository, 'code'))

            ->setDefault('notes', function (Options $options): string {
                return $this->faker->paragraphs(1, true);
            })
            ->setAllowedTypes('notes', ['null', 'string'])

            ->setDefault('items', [])
            ->setAllowedTypes('items', ['array'])

            ->setDefault('gift_card_codes', function(Options $options){
                $amount = rand(1, 3);

                /** @var ChannelInterface $channel */
                $channel = $options['channel'];

                /** @var CurrencyInterface $currency */
                $currency = $options['currency'];

                $objects = $this->giftCardCodeRepository->findBy([
                    'channel' => $channel,
                    // 'currencyCode' => $currency->getCode(),
                ]);

                if ($objects instanceof Collection) {
                    $objects = $objects->toArray();
                }

                Assert::notEmpty($objects, sprintf(
                    "Unable to find gift card codes for channel '%s' in %s",
                    $channel,
                    $currency
                ));

                $selectedObjects = [];
                for (; $amount > 0 && count($objects) > 0; --$amount) {
                    $randomKey = array_rand($objects);

                    $selectedObjects[] = $objects[$randomKey];

                    unset($objects[$randomKey]);
                }

                return $selectedObjects;
            })
            ->setAllowedTypes('gift_card_codes', ['array'])
            ->setNormalizer('gift_card_codes', LazyOption::findBy($this->giftCardCodeRepository, 'code'))

            ->setDefault('address', [])
            ->setAllowedTypes('address', ['array', AddressInterface::class])

            ->setDefault('shipping_method', LazyOption::randomOne($this->shippingMethodRepository))
            ->setAllowedTypes('shipping_method', ['string', ShippingMethodInterface::class])
            ->setNormalizer('shipping_method', LazyOption::findOneBy($this->shippingMethodRepository, 'code'))

            ->setDefault('payment_method', LazyOption::randomOne($this->paymentMethodRepository))
            ->setAllowedTypes('payment_method', ['string', PaymentMethodInterface::class])
            ->setNormalizer('payment_method', LazyOption::findOneBy($this->paymentMethodRepository, 'code'))

            ->setDefault('checkout_completed_at', $this->faker->dateTimeBetween('-1 years', 'now'))
            ->setAllowedTypes('checkout_completed_at', ['null', 'string', \DateTimeInterface::class])
            ->setNormalizer('checkout_completed_at', function(Options $options, $value): \DateTimeInterface{
                return new \DateTime($value);
            })
        ;
    }

    protected function configureOrderItemOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('product', LazyOption::randomOne($this->productRepository))
            ->setAllowedTypes('product', ['string', ProductInterface::class])
            ->setNormalizer('product', LazyOption::findOneBy($this->productRepository, 'code'))

            ->setDefault('variant', function(Options $options): ProductVariantInterface {
                $product = $options['product'];
                if (!$product instanceof ProductInterface) {
                    throw new \RuntimeException(sprintf(
                        "You should specify 'product' or 'variant' option with valid code, but only %s options specified.",
                        implode(' ,', array_keys($options))
                    ));
                }
                return $this->faker->randomElement($product->getVariants()->toArray());
            })
            ->setAllowedTypes('variant', ['string', ProductVariantInterface::class])
            ->setNormalizer('variant', LazyOption::findOneBy($this->productVariantRepository, 'code'))

            ->setDefault('quantity', $this->faker->randomNumber(1))
        ;
    }

    protected function configureAddressOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('first_name', $this->faker->firstName)
            ->setDefault('last_name', $this->faker->lastName)
            ->setDefault('street', $this->faker->streetAddress)

            ->setDefault('country', function(Options $options): CountryInterface {
                $countries = $this->countryRepository->findAll();
                return $this->faker->randomElement($countries);
            })
            ->setAllowedTypes('country', ['string', CountryInterface::class])
            ->setNormalizer('country', LazyOption::findOneBy($this->countryRepository, 'code'))

            ->setDefault('city', $this->faker->city)
            ->setDefault('postcode', $this->faker->postcode)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): OrderInterface
    {
        $options = $this->optionsResolver->resolve($options);
        $order = $this->createOrder($options);
        $this->addOrderItems($order, $options);
        $this->addGiftCards($order, $options);
        $this->fillAddress($order, $options);
        $this->selectShipping($order, $options);
        $this->selectPayment($order, $options);
        $this->completeCheckout($order, $options);
        $this->setOrderCompletedDate($order, $options);

        return $order;
    }

    protected function createOrder(array $orderOptions = []): OrderInterface
    {
        /** @var CurrencyInterface $currency */
        $currency = $orderOptions['currency'];

        /** @var Locale $locale */
        $locale = $orderOptions['locale'];

        /** @var OrderInterface $order */
        $order = $this->orderFactory->createNew();
        $order->setChannel($orderOptions['channel']);
        $order->setCustomer($orderOptions['customer']);
        $order->setCurrencyCode($currency->getCode());
        $order->setLocaleCode($locale->getCode());

        return $order;
    }

    protected function addOrderItems(OrderInterface $order, array $orderOptions = []): void
    {
        foreach ($orderOptions['items'] as $orderItemOptions) {
            $orderItemOptionsResolver = new OptionsResolver();
            $this->configureOrderItemOptions($orderItemOptionsResolver);
            $orderItemOptions = $orderItemOptionsResolver->resolve($orderItemOptions);
            $orderItem = $this->createOrderItem($orderItemOptions);

            $order->addItem($orderItem);
        }
    }

    protected function addGiftCards(OrderInterface $order, array $orderOptions = []): void
    {
        /** @var GiftCardCodeInterface $giftCardCode */
        foreach ($orderOptions['gift_card_codes'] as $giftCardCode) {
            $giftCardCode->setCurrentOrder($order);
        }
    }

    protected function createOrderItem(array $orderItemOptions = []): OrderItemInterface
    {
        /** @var OrderItemInterface $item */
        $item = $this->orderItemFactory->createNew();

        $item->setVariant($orderItemOptions['variant']);
        $this->orderItemQuantityModifier->modify($item, $orderItemOptions['quantity']);

        return $item;
    }

    protected function createAddress(array $addressOptions = []): AddressInterface
    {
        /** @var CountryInterface $country */
        $country = $addressOptions['country'];

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName($addressOptions['first_name']);
        $address->setLastName($addressOptions['last_name']);
        $address->setStreet($addressOptions['street']);
        $address->setCountryCode($country->getCode());
        $address->setCity($addressOptions['city']);
        $address->setPostcode($addressOptions['postcode']);

        return $address;
    }

    protected function fillAddress(OrderInterface $order, array $options = []): void
    {
        $addressOptionsResolver = new OptionsResolver();
        $this->configureAddressOptions($addressOptionsResolver);
        $addressOptions = $options['address'];
        $addressOptions = $addressOptionsResolver->resolve($addressOptions);
        $address = $this->createAddress($addressOptions);

        $order->setShippingAddress($address);
        $order->setBillingAddress(clone $address);

        $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);
    }


    private function selectShipping(OrderInterface $order, array $options = []): void
    {
        if ($this->orderShippingMethodSelectionRequirementChecker->isShippingMethodSelectionRequired($order)) {
            $shippingMethod = $this
                ->faker
                ->randomElement($this->shippingMethodRepository->findEnabledForChannel($order->getChannel()))
            ;

            /** @var ChannelInterface $channel */
            $channel = $order->getChannel();
            Assert::notNull($shippingMethod, sprintf(
                "No enabled shipping method was found for channel '%s'. " .
                "Set 'skipping_shipping_step_allowed' option to true for this channel if you want to skip shipping method selection.",
                $channel->getCode()
            ));

            foreach ($order->getShipments() as $shipment) {
                $shipment->setMethod($shippingMethod);
            }

            $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        } else {
            $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_SKIP_SHIPPING);
        }
    }

    private function selectPayment(OrderInterface $order, array $options = []): void
    {
        if ($this->orderPaymentMethodSelectionRequirementChecker->isPaymentMethodSelectionRequired($order)) {
            $paymentMethod = $this
                ->faker
                ->randomElement($this->paymentMethodRepository->findEnabledForChannel($order->getChannel()))
            ;

            /** @var ChannelInterface $channel */
            $channel = $order->getChannel();
            Assert::notNull($paymentMethod, sprintf(
                "No enabled payment method was found for channel '%s'. " .
                "Set 'skipping_payment_step_allowed' option to true for this channel if you want to skip payment method selection.",
                $channel->getCode()
            ));

            foreach ($order->getPayments() as $payment) {
                $payment->setMethod($paymentMethod);
            }

            $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        } else {
            $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_SKIP_PAYMENT);
        }
    }

    protected function completeCheckout(OrderInterface $order, array $options = []): void
    {
        $order->setNotes($options['notes']);

        $this->applyCheckoutStateTransition($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
    }

    private function setOrderCompletedDate(OrderInterface $order, array $options = []): void
    {
        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_COMPLETED) {
            $order->setCheckoutCompletedAt($options['checkout_completed_at']);
        }
    }

    private function applyCheckoutStateTransition(OrderInterface $order, string $transition): void
    {
        $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH)->apply($transition);
    }
}
