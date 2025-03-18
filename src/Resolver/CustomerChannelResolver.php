<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use RuntimeException;
use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

final class CustomerChannelResolver implements CustomerChannelResolverInterface
{
    public function __construct(private readonly OrderRepositoryInterface $orderRepository, private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public function resolve(CustomerInterface $customer): ChannelInterface
    {
        $latestOrder = $this->orderRepository->findLatestByCustomer($customer);
        if ($latestOrder instanceof OrderInterface) {
            $channel = $latestOrder->getChannel();
            if ($channel instanceof \Sylius\Component\Channel\Model\ChannelInterface) {
                return $channel;
            }
        }

        /** @var ChannelInterface|null $channel */
        $channel = $this->channelRepository->findOneBy([
            'enabled' => true,
        ]);

        if (null === $channel) {
            throw new RuntimeException('There are no enabled channels');
        }

        return $channel;
    }
}
