<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\Persistence\ManagerRegistry;
use Payum\Core\Model\GatewayConfigInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardPaymentMethodProvider;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

/**
 * The provider lazily creates the offline gift card payment method used in payment mode. Its services are only wired
 * only in the payment redemption service file, so it is constructed directly here against the real Sylius factory/repository.
 */
final class GiftCardPaymentMethodProviderTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_lazily_creates_a_persistable_offline_gift_card_payment_method(): void
    {
        $channel = $this->getChannel();
        $provider = $this->createProvider();

        $paymentMethod = $provider->getPaymentMethod($channel);

        // The flush inside the provider must not throw: gateway_name is a NOT NULL column, so createWithGateway()
        // alone (which only sets the factory name) would fail without our explicit setGatewayName()
        self::assertSame('gift_card', $paymentMethod->getCode());
        self::assertTrue($paymentMethod->isEnabled());
        self::assertTrue($paymentMethod->getChannels()->contains($channel));

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        self::assertInstanceOf(GatewayConfigInterface::class, $gatewayConfig);
        self::assertSame('offline', $gatewayConfig->getFactoryName());
        self::assertSame('gift_card', $gatewayConfig->getGatewayName());
    }

    /** @test */
    public function it_returns_the_existing_payment_method_on_subsequent_calls(): void
    {
        $channel = $this->getChannel();
        $provider = $this->createProvider();

        $first = $provider->getPaymentMethod($channel);
        $this->manager->clear();

        $second = $provider->getPaymentMethod($channel);

        self::assertSame($first->getId(), $second->getId());
    }

    private function createProvider(): GiftCardPaymentMethodProvider
    {
        $container = self::getContainer();

        /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface> $repository */
        $repository = $container->get('sylius.repository.payment_method');

        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = $container->get('sylius.factory.payment_method');

        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = $container->get('doctrine');

        return new GiftCardPaymentMethodProvider($repository, $factory, $managerRegistry, 'gift_card');
    }
}
