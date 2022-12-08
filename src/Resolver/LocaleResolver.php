<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class LocaleResolver implements LocaleResolverInterface
{
    private OrderRepositoryInterface $orderRepository;

    private ChannelRepositoryInterface $channelRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ChannelRepositoryInterface $channelRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->channelRepository = $channelRepository;
    }

    public function resolveFromCustomer(CustomerInterface $customer): string
    {
        $latestOrder = $this->orderRepository->findLatestByCustomer($customer);
        if (null !== $latestOrder) {
            return $this->resolveFromOrder($latestOrder);
        }

        return $this->resolve();
    }

    public function resolveFromOrder(OrderInterface $order): string
    {
        $localeCode = $order->getLocaleCode();
        if (null !== $localeCode) {
            return $localeCode;
        }

        $channel = $order->getChannel();
        if (null !== $channel) {
            return $this->resolveFromChannel($channel);
        }

        return $this->resolve();
    }

    public function resolveFromChannel(ChannelInterface $channel): string
    {
        return $this->_resolveFromChannel($channel) ?? $this->resolve();
    }

    /**
     * This is a fallback
     */
    private function resolve(): string
    {
        /** @var list<ChannelInterface> $channels */
        $channels = $this->channelRepository->findBy([
            'enabled' => true,
        ]);

        foreach ($channels as $channel) {
            $localeCode = $this->_resolveFromChannel($channel);
            if (null !== $localeCode) {
                return $localeCode;
            }
        }

        throw new \RuntimeException('Could not resolve a locale');
    }

    private function _resolveFromChannel(ChannelInterface $channel): ?string
    {
        $locale = $channel->getDefaultLocale();
        if (null !== $locale) {
            $localeCode = $locale->getCode();
            if (null !== $localeCode) {
                return $localeCode;
            }
        }

        foreach ($channel->getLocales() as $locale) {
            $localeCode = $locale->getCode();
            if (null !== $localeCode) {
                return $localeCode;
            }
        }

        return null;
    }
}
