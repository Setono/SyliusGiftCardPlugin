<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Applicator;

use Setono\SyliusGiftCardPlugin\Exception\ChannelMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardCurrencyMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardNotFoundException;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Webmozart\Assert\Assert;

final class GiftCardApplicator implements GiftCardApplicatorInterface
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly GiftCardCodeNormalizerInterface $codeNormalizer,
        private readonly GiftCardRedemptionMethodInterface $redemptionMethod,
    ) {
    }

    public function apply(OrderInterface $order, $giftCard): void
    {
        $giftCard = $this->resolveGiftCard($giftCard);

        if ($order->hasGiftCard($giftCard)) {
            return;
        }

        Assert::true($giftCard->isUsable(), 'The gift card is not usable');
        Assert::notEq(
            $order->getCheckoutState(),
            OrderCheckoutStates::STATE_COMPLETED,
            'A gift card cannot be applied to a completed order',
        );

        $orderChannel = $order->getChannel();
        Assert::isInstanceOf($orderChannel, ChannelInterface::class);

        $giftCardChannel = $giftCard->getChannel();
        Assert::isInstanceOf($giftCardChannel, ChannelInterface::class);

        if ($orderChannel->getCode() !== $giftCardChannel->getCode()) {
            throw new ChannelMismatchException($giftCardChannel, $orderChannel);
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null !== $orderCurrencyCode && $giftCard->getCurrencyCode() !== $orderCurrencyCode) {
            throw new GiftCardCurrencyMismatchException($giftCard, $orderCurrencyCode);
        }

        $this->redemptionMethod->apply($order, $giftCard);
    }

    public function remove(OrderInterface $order, $giftCard): void
    {
        $giftCard = $this->resolveGiftCard($giftCard);

        if (!$order->hasGiftCard($giftCard)) {
            return;
        }

        $this->redemptionMethod->remove($order, $giftCard);
    }

    /**
     * @param string|GiftCardInterface $giftCard
     */
    private function resolveGiftCard($giftCard): GiftCardInterface
    {
        if ($giftCard instanceof GiftCardInterface) {
            return $giftCard;
        }

        $code = $this->codeNormalizer->normalize($giftCard);

        $resolved = $this->giftCardRepository->findOneByCode($code);
        if (null === $resolved) {
            throw new GiftCardNotFoundException($code);
        }

        return $resolved;
    }
}
