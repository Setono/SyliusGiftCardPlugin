<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ChannelTrait
{
    /**
     * @var GiftCardConfigurationInterface|null
     *
     * @ORM\ManyToOne(targetEntity="Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface", inversedBy="channels")
     */
    protected $giftCardConfiguration;

    public function setGiftCardConfiguration(?GiftCardConfigurationInterface $giftCardConfiguration): void
    {
        $this->giftCardConfiguration = $giftCardConfiguration;
    }

    public function getGiftCardConfiguration(): ?GiftCardConfigurationInterface
    {
        return $this->giftCardConfiguration;
    }
}
