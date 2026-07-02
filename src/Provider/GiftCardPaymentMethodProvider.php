<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

final class GiftCardPaymentMethodProvider implements GiftCardPaymentMethodProviderInterface
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     */
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethodRepository, private readonly PaymentMethodFactoryInterface $paymentMethodFactory, private readonly ObjectManager $paymentMethodManager, private readonly string $paymentMethodCode, private readonly LoggerInterface $logger = new NullLogger())
    {
    }

    public function getPaymentMethod(ChannelInterface $channel): PaymentMethodInterface
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $this->paymentMethodCode]);
        if ($paymentMethod instanceof PaymentMethodInterface) {
            return $paymentMethod;
        }

        return $this->createPaymentMethod($channel);
    }

    private function createPaymentMethod(ChannelInterface $channel): PaymentMethodInterface
    {
        $paymentMethod = $this->paymentMethodFactory->createWithGateway('offline');
        $paymentMethod->setCode($this->paymentMethodCode);
        $paymentMethod->setEnabled(true);

        $localeCode = $channel->getDefaultLocale()?->getCode() ?? 'en_US';
        $paymentMethod->setCurrentLocale($localeCode);
        $paymentMethod->setFallbackLocale($localeCode);
        $paymentMethod->setName('Gift card');

        $paymentMethod->addChannel($channel);

        $this->paymentMethodManager->persist($paymentMethod);
        $this->paymentMethodManager->flush();

        $this->logger->info(sprintf(
            'Created the "%s" gift card payment method because it did not exist yet',
            $this->paymentMethodCode,
        ));

        return $paymentMethod;
    }
}
