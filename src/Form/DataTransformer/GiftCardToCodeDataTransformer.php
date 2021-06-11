<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Webmozart\Assert\Assert;

final class GiftCardToCodeDataTransformer implements DataTransformerInterface
{
    private GiftCardRepositoryInterface $giftCardRepository;

    private ChannelContextInterface $channelContext;

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

        $giftCard = $this->giftCardRepository->findOneEnabledByCodeAndChannel(
            $value,
            $this->channelContext->getChannel()
        );

        if (null !== $giftCard) {
            return $giftCard;
        }

        throw new TransformationFailedException('setono_sylius_gift_card.ui.gift_card_code_does_not_exist');
    }
}
