<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GiftCardPaymentMethodFactory implements GiftCardPaymentMethodFactoryInterface
{
    /**
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface> $paymentMethodFactory
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly PaymentMethodFactoryInterface $paymentMethodFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly string $paymentMethodCode,
        private readonly TranslatorInterface $translator,
        private readonly RepositoryInterface $localeRepository,
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

        // The name is what the order pages, the customer's account and the admin show for a gift card payment, so it
        // is given in the language of every locale of the shop. The fallback locale moves along with the current one,
        // or the translation of the fallback locale would be renamed instead of a new one being added
        $localeCodes = $this->getLocaleCodes($channels);
        foreach ($localeCodes as $localeCode) {
            $paymentMethod->setCurrentLocale($localeCode);
            $paymentMethod->setFallbackLocale($localeCode);
            $paymentMethod->setName($this->translator->trans('setono_sylius_gift_card.ui.gift_card', [], 'messages', $localeCode));
        }
        $paymentMethod->setCurrentLocale($localeCodes[0]);
        $paymentMethod->setFallbackLocale($localeCodes[0]);

        foreach ($channels as $channel) {
            $paymentMethod->addChannel($channel);
        }

        return $paymentMethod;
    }

    /**
     * @param list<ChannelInterface> $channels
     *
     * @return non-empty-list<string> the default locale of every channel first, then every other locale of the shop,
     *                                and en_US when the shop has neither yet
     */
    private function getLocaleCodes(array $channels): array
    {
        $localeCodes = [];
        foreach ($channels as $channel) {
            $localeCodes[] = (string) $channel->getDefaultLocale()?->getCode();
        }

        /** @var LocaleInterface $locale */
        foreach ($this->localeRepository->findAll() as $locale) {
            $localeCodes[] = (string) $locale->getCode();
        }

        $localeCodes = array_values(array_unique(array_filter($localeCodes, static fn (string $code): bool => '' !== $code)));

        return [] === $localeCodes ? ['en_US'] : $localeCodes;
    }
}
