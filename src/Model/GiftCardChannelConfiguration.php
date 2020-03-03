<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Channel\Model\ChannelInterface as SyliusChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;

class GiftCardChannelConfiguration implements GiftCardChannelConfigurationInterface
{
    /** @var int|null */
    protected $id;

    /** @var SyliusChannelInterface|null */
    protected $channel;

    /** @var LocaleInterface|null */
    protected $locale;

    /** @var GiftCardConfigurationInterface|null */
    protected $configuration;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?SyliusChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?SyliusChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getLocale(): ?LocaleInterface
    {
        return $this->locale;
    }

    public function setLocale(?LocaleInterface $locale): void
    {
        $this->locale = $locale;
    }

    public function getConfiguration(): ?GiftCardConfigurationInterface
    {
        return $this->configuration;
    }

    public function setConfiguration(?GiftCardConfigurationInterface $configuration): void
    {
        $this->configuration = $configuration;
    }
}
