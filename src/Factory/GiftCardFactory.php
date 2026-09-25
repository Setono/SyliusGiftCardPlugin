<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Factory;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardExpiryResolverInterface;
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
        private readonly GiftCardExpiryResolverInterface $giftCardExpiryResolver,
    ) {
    }

    public function createNew(): GiftCardInterface
    {
        $giftCard = $this->decoratedFactory->createNew();
        $giftCard->setCode($this->giftCardCodeGenerator->generate());
        $giftCard->setExpiresAt($this->giftCardExpiryResolver->resolve());

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
}
