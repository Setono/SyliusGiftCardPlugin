<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\Doctrine\ORMTrait;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

final class GiftCardPaymentMethodProvider implements GiftCardPaymentMethodProviderInterface
{
    use ORMTrait;

    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     */
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethodRepository, private readonly PaymentMethodFactoryInterface $paymentMethodFactory, ManagerRegistry $managerRegistry, private readonly string $paymentMethodCode, private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->managerRegistry = $managerRegistry;
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

        // createWithGateway() only sets the gateway config factory name; gatewayName is a NOT NULL column, so set it too
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null !== $gatewayConfig) {
            $gatewayConfig->setGatewayName($this->paymentMethodCode);
        }

        $localeCode = $channel->getDefaultLocale()?->getCode() ?? 'en_US';
        $paymentMethod->setCurrentLocale($localeCode);
        $paymentMethod->setFallbackLocale($localeCode);
        $paymentMethod->setName('Gift card');

        $paymentMethod->addChannel($channel);

        $manager = $this->getManager($paymentMethod);
        $manager->persist($paymentMethod);
        $manager->flush();

        $this->logger->info(sprintf(
            'Created the "%s" gift card payment method because it did not exist yet',
            $this->paymentMethodCode,
        ));

        return $paymentMethod;
    }
}
