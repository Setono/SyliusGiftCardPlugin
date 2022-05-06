<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Webmozart\Assert\Assert;

class GiftCardChannelConfiguration implements GiftCardChannelConfigurationInterface
{
    protected ?int $id = null;

    protected ?ChannelInterface $channel = null;

    protected ?LocaleInterface $locale = null;

    protected ?GiftCardConfigurationInterface $configuration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        Assert::notNull($channel);
        $this->channel = $channel;
    }

    public function getLocale(): ?LocaleInterface
    {
        return $this->locale;
    }

    public function setLocale(LocaleInterface $locale): void
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
