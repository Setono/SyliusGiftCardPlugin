<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class StrictGiftCardToCodeDataTransformer extends GiftCardToCodeDataTransformer
{
    public function reverseTransform($value): ?GiftCardInterface
    {
        $giftCard = parent::reverseTransform($value);
        if (null !== $giftCard) {
            return $giftCard;
        }

        throw new TransformationFailedException('setono_sylius_gift_card.ui.gift_card_code_does_not_exist');
    }
}
