<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use RuntimeException;
use Setono\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class GiftCardApplicator implements GiftCardApplicatorInterface
{
    public function __construct(private readonly GiftCardRepositoryInterface $giftCardRepository, private readonly OrderProcessorInterface $orderProcessor)
    {
    }

    /**
     * @param string|GiftCardInterface $giftCard
     */
    public function apply(OrderInterface $order, $giftCard): void
    {
        if (is_string($giftCard)) {
            $giftCard = $this->getGiftCard($giftCard);
        }

        if (!$giftCard->isEnabled()) {
            throw new RuntimeException('The gift card is not enabled');
        }

        if ($giftCard->isExpired()) {
            throw new RuntimeException('The gift card is expired');
        }

        $orderChannel = $order->getChannel();
        if (null === $orderChannel) {
            throw new RuntimeException('The channel on the order cannot be null');
        }

        $giftCardChannel = $giftCard->getChannel();
        if (null === $giftCardChannel) {
            throw new RuntimeException('The channel on the gift card cannot be null');
        }

        if ($orderChannel->getCode() !== $giftCardChannel->getCode()) {
            throw new ChannelMismatchException($giftCardChannel, $orderChannel);
        }

        $order->addGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    /**
     * @param string|GiftCardInterface $giftCard
     */
    public function remove(OrderInterface $order, $giftCard): void
    {
        if (is_string($giftCard)) {
            $giftCard = $this->getGiftCard($giftCard);
        }

        $order->removeGiftCard($giftCard);

        $this->orderProcessor->process($order);
    }

    private function getGiftCard(string $giftCardCode): GiftCardInterface
    {
        $giftCard = $this->giftCardRepository->findOneByCode($giftCardCode);

        if (null === $giftCard) {
            throw new GiftCardNotFoundException($giftCardCode);
        }

        return $giftCard;
    }
}
