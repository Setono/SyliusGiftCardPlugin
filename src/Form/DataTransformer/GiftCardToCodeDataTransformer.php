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
    public function __construct(private readonly GiftCardRepositoryInterface $giftCardRepository, private readonly ChannelContextInterface $channelContext)
    {
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

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected the value to be a string');
        }

        $giftCard = $this->giftCardRepository->findOneEnabledByCodeAndChannel(
            $value,
            $this->channelContext->getChannel(),
        );

        if ($giftCard instanceof GiftCardInterface) {
            return $giftCard;
        }

        throw new TransformationFailedException('setono_sylius_gift_card.ui.gift_card_code_does_not_exist');
    }
}
