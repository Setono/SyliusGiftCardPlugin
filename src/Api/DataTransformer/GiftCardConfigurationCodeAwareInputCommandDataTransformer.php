<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Api\DataTransformer;

use Setono\SyliusGiftCardPlugin\Api\Command\ConfigurationCodeAwareInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Bundle\ApiBundle\DataTransformer\CommandDataTransformerInterface;

final class GiftCardConfigurationCodeAwareInputCommandDataTransformer implements CommandDataTransformerInterface
{
    /**
     * @param ConfigurationCodeAwareInterface $object
     */
    public function transform($object, string $to, array $context = []): ConfigurationCodeAwareInterface
    {
        /** @var GiftCardConfigurationInterface $giftCardConfiguration */
        $giftCardConfiguration = $context['object_to_populate'];

        $object->setConfigurationCode($giftCardConfiguration->getCode());

        return $object;
    }

    /**
     * @param object $object
     */
    public function supportsTransformation($object): bool
    {
        return $object instanceof ConfigurationCodeAwareInterface;
    }
}
