<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class GiftCardFactory implements GiftCardFactoryInterface
{
    /**
     * @param FactoryInterface<GiftCardInterface> $decoratedFactory
     */
    public function __construct(
        private readonly FactoryInterface $decoratedFactory,
        private readonly GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        private readonly ?string $defaultValidityPeriod,
    ) {
    }

    public function createNew(): GiftCardInterface
    {
        $giftCard = $this->decoratedFactory->createNew();
        $giftCard->setCode($this->giftCardCodeGenerator->generate());
        $giftCard->setExpiresAt($this->resolveExpiresAt());

        return $giftCard;
    }

    public function createForChannel(ChannelInterface $channel): GiftCardInterface
    {
        $giftCard = $this->createNew();
        $giftCard->setChannel($channel);

        $baseCurrency = $channel->getBaseCurrency();
        if (null !== $baseCurrency && null !== $baseCurrency->getCode()) {
            $giftCard->setCurrencyCode($baseCurrency->getCode());
        }

        return $giftCard;
    }

    private function resolveExpiresAt(): ?\DateTimeImmutable
    {
        if (null === $this->defaultValidityPeriod) {
            return null;
        }

        // A gift card stays valid through the end of its expiry day
        return (new \DateTimeImmutable('+' . $this->defaultValidityPeriod))->setTime(23, 59, 59);
    }
}
