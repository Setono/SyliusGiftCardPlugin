<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Doctrine\Common\Persistence\ObjectManager;
use RuntimeException;
use Setono\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class GiftCardApplicator implements GiftCardApplicatorInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var OrderProcessorInterface */
    private $orderProcessor;

    /** @var ObjectManager */
    private $orderManager;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        OrderProcessorInterface $orderProcessor,
        ObjectManager $orderManager
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->orderProcessor = $orderProcessor;
        $this->orderManager = $orderManager;
    }

    /**
     * @param string|GiftCardInterface|mixed $giftCard
     */
    public function apply(OrderInterface $order, $giftCard): void
    {
        if (is_string($giftCard)) {
            $giftCard = $this->getGiftCard($giftCard);
        }

        if (!$giftCard instanceof GiftCardInterface) {
            throw new GiftCardNotFoundException($giftCard);
        }

        $orderChannel = $order->getChannel();
        if (null === $orderChannel) {
            throw new RuntimeException('The channel on the order cannot be null');
        }

        $giftCardChannel = $giftCard->getChannel();
        if (null === $giftCardChannel) {
            throw new RuntimeException('The channel on the gift card cannot be null');
        }

        if ($orderChannel !== $giftCardChannel) {
            throw new ChannelMismatchException($giftCardChannel, $orderChannel);
        }

        $order->addGiftCard($giftCard);

        $this->orderProcessor->process($order);

        $this->orderManager->flush();
    }

    /**
     * @param string|GiftCardInterface|mixed $giftCard
     */
    public function remove(OrderInterface $order, $giftCard): void
    {
        if (is_string($giftCard)) {
            $giftCard = $this->getGiftCard($giftCard);
        }

        if (!$giftCard instanceof GiftCardInterface) {
            throw new GiftCardNotFoundException($giftCard);
        }

        $order->removeGiftCard($giftCard);

        $this->orderProcessor->process($order);

        $this->orderManager->flush();
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
