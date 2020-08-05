<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Setono\SyliusGiftCardPlugin\Repository\OrderRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;

final class CustomerChannelResolver implements CustomerChannelResolverInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ChannelRepositoryInterface $channelRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->channelRepository = $channelRepository;
    }

    public function resolve(CustomerInterface $customer): ChannelInterface
    {
        $latestOrder = $this->orderRepository->findLatestByCustomer($customer);
        if (null !== $latestOrder) {
            $channel = $latestOrder->getChannel();
            if (null !== $channel) {
                return $channel;
            }
        }

        /** @var ChannelInterface|null $channel */
        $channel = $this->channelRepository->findOneBy([
            'enabled' => true,
        ]);

        if (null === $channel) {
            throw new \RuntimeException('There are no enabled channels');
        }

        return $channel;
    }
}
