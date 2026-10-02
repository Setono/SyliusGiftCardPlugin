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
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GiftCardPaymentMethodProvider implements GiftCardPaymentMethodProviderInterface
{
    use ORMTrait;

    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodFactoryInterface $paymentMethodFactory,
        ManagerRegistry $managerRegistry,
        private readonly string $paymentMethodCode,
        private readonly TranslatorInterface $translator,
        private readonly RepositoryInterface $localeRepository,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
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

        // The name is what the order pages, the customer's account and the admin show for a gift card payment, so it
        // is given in the language of every locale of the shop. The fallback locale moves along with the current one,
        // or the translation of the fallback locale would be renamed instead of a new one being added
        $defaultLocaleCode = $channel->getDefaultLocale()?->getCode() ?? 'en_US';
        foreach ($this->getLocaleCodes($defaultLocaleCode) as $localeCode) {
            $paymentMethod->setCurrentLocale($localeCode);
            $paymentMethod->setFallbackLocale($localeCode);
            $paymentMethod->setName($this->translator->trans('setono_sylius_gift_card.ui.gift_card', [], 'messages', $localeCode));
        }
        $paymentMethod->setCurrentLocale($defaultLocaleCode);
        $paymentMethod->setFallbackLocale($defaultLocaleCode);

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

    /**
     * @return list<string> the channel's default locale first, then every other locale of the shop
     */
    private function getLocaleCodes(string $defaultLocaleCode): array
    {
        $localeCodes = [$defaultLocaleCode];

        /** @var LocaleInterface $locale */
        foreach ($this->localeRepository->findAll() as $locale) {
            $localeCodes[] = (string) $locale->getCode();
        }

        return array_values(array_unique(array_filter($localeCodes, static fn (string $code): bool => '' !== $code)));
    }
}
