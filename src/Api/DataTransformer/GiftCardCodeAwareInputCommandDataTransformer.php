<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\DataTransformer;

use Setono\SyliusGiftCardPlugin\Api\Command\GiftCardCodeAwareInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ApiBundle\DataTransformer\CommandDataTransformerInterface;

final class GiftCardCodeAwareInputCommandDataTransformer implements CommandDataTransformerInterface
{
    /**
     * @param GiftCardCodeAwareInterface $object
     */
    public function transform($object, string $to, array $context = []): GiftCardCodeAwareInterface
    {
        /** @var GiftCardInterface $giftCard */
        $giftCard = $context['object_to_populate'];

        $object->setGiftCardCode($giftCard->getCode());

        return $object;
    }

    /**
     * @param object $object
     */
    public function supportsTransformation($object): bool
    {
        return $object instanceof GiftCardCodeAwareInterface;
    }
}
