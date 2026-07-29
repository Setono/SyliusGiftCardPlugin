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

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    #[\Override]
    public function setChannel(?ChannelInterface $channel): void
    {
        Assert::notNull($channel);
        $this->channel = $channel;
    }

    #[\Override]
    public function getLocale(): ?LocaleInterface
    {
        return $this->locale;
    }

    #[\Override]
    public function setLocale(LocaleInterface $locale): void
    {
        $this->locale = $locale;
    }

    #[\Override]
    public function getConfiguration(): ?GiftCardConfigurationInterface
    {
        return $this->configuration;
    }

    #[\Override]
    public function setConfiguration(?GiftCardConfigurationInterface $configuration): void
    {
        $this->configuration = $configuration;
    }
}
