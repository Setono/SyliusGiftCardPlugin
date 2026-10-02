<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class GiftCardPaymentMethodFactory implements GiftCardPaymentMethodFactoryInterface
{
    /**
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly PaymentMethodFactoryInterface $paymentMethodFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly string $paymentMethodCode,
    ) {
    }

    public function create(): PaymentMethodInterface
    {
        $paymentMethod = $this->paymentMethodFactory->createWithGateway('offline');
        $paymentMethod->setCode($this->paymentMethodCode);
        $paymentMethod->setEnabled(true);

        // createWithGateway() only sets the gateway config factory name; gatewayName is a NOT NULL column, so set it too
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null !== $gatewayConfig) {
            $gatewayConfig->setGatewayName($this->paymentMethodCode);
        }

        /** @var list<ChannelInterface> $channels */
        $channels = $this->channelRepository->findAll();

        // The name is what the order pages show for a gift card payment, so the method is named in the default locale
        // of every channel rather than in a locale a channel may not have (in en_US when there is no channel yet)
        foreach ([] === $channels ? [null] : $channels as $channel) {
            $localeCode = $channel?->getDefaultLocale()?->getCode() ?? 'en_US';
            $paymentMethod->setCurrentLocale($localeCode);
            $paymentMethod->setFallbackLocale($localeCode);
            $paymentMethod->setName('Gift card');
        }

        foreach ($channels as $channel) {
            $paymentMethod->addChannel($channel);
        }

        return $paymentMethod;
    }
}
