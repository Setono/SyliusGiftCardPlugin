<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Fixture;

use Doctrine\Persistence\ManagerRegistry;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardPaymentMethodFactoryInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProviderInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;

/**
 * Sets up the payment method gift card payments are made with, the way setono:gift-card:create-payment-method does,
 * so a shop seeded with fixtures takes gift cards straight away. It runs after the channels exist and leaves an
 * existing method alone
 */
class GiftCardPaymentMethodFixture extends AbstractFixture
{
    use ORMTrait;

    public function __construct(
        private readonly GiftCardPaymentMethodProviderInterface $paymentMethodProvider,
        private readonly GiftCardPaymentMethodFactoryInterface $paymentMethodFactory,
        ManagerRegistry $managerRegistry,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function getName(): string
    {
        return 'setono_gift_card_payment_method';
    }

    public function load(array $options): void
    {
        if (null !== $this->paymentMethodProvider->findPaymentMethod()) {
            return;
        }

        $paymentMethod = $this->paymentMethodFactory->create();

        $manager = $this->getManager($paymentMethod);
        $manager->persist($paymentMethod);
        $manager->flush();
    }
}
