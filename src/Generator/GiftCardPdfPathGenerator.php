<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Webmozart\Assert\Assert;
use function sprintf;

final class GiftCardPdfPathGenerator implements GiftCardPdfPathGeneratorInterface
{
    public function generatePath(GiftCardConfigurationInterface $giftCardChannelConfiguration): string
    {
        $giftCardChannelConfigurationId = $giftCardChannelConfiguration->getId();
        Assert::integer($giftCardChannelConfigurationId);

        return sprintf(
            'gift_card_configuration_pdf_%d.pdf',
            $giftCardChannelConfigurationId
        );
    }
}
