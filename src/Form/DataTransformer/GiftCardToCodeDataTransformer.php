<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Webmozart\Assert\Assert;

class GiftCardToCodeDataTransformer implements DataTransformerInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    public function __construct(
        GiftCardRepositoryInterface $giftCardRepository,
        ChannelContextInterface $channelContext
    ) {
        $this->giftCardRepository = $giftCardRepository;
        $this->channelContext = $channelContext;
    }

    /**
     * @param GiftCardInterface|mixed $value
     */
    public function transform($value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        Assert::isInstanceOf($value, GiftCardInterface::class);

        return $value->getCode();
    }

    public function reverseTransform($value): ?GiftCardInterface
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return $this->giftCardRepository->findOneEnabledByCodeAndChannel(
            $value,
            $this->channelContext->getChannel()
        );
    }
}
