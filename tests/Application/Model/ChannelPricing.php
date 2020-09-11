<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Application\Model;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCatalogPromotionPlugin\Model\ChannelPricingInterface as CatalogPromotionChannelPricingInterface;
use Setono\SyliusCatalogPromotionPlugin\Model\ChannelPricingTrait as CatalogPromotionChannelPricingTrait;
use Sylius\Component\Core\Model\ChannelPricing as BaseChannelPricing;

/**
 * @ORM\Table(name="sylius_channel_pricing")
 * @ORM\Entity()
 */
class ChannelPricing extends BaseChannelPricing implements CatalogPromotionChannelPricingInterface
{
    use CatalogPromotionChannelPricingTrait;
}
