<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;

class GiftCardConfiguration implements GiftCardConfigurationInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    /** @var int|null */
    protected $id;

    /** @var string|null */
    protected $code;

    /** @var ImageInterface[]|Collection */
    protected $images;

    /** @var GiftCardChannelConfigurationInterface[]|Collection */
    protected $channelConfigurations;

    /** @var bool|null */
    protected $default = false;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->channelConfigurations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getImagesByType(string $type): Collection
    {
        return $this->images->filter(function (ImageInterface $image) use ($type): bool {
            return $image->getType() === $type;
        });
    }

    public function hasImages(): bool
    {
        return !$this->getImages()->isEmpty();
    }

    public function hasImage(ImageInterface $image): bool
    {
        return $this->getImages()->contains($image);
    }

    public function addImage(ImageInterface $image): void
    {
        if (!$this->hasImage($image)) {
            $image->setOwner($this);
            $this->images->add($image);
        }
    }

    public function removeImage(ImageInterface $image): void
    {
        if ($this->hasImage($image)) {
            $image->setOwner(null);
            $this->images->removeElement($image);
        }
    }

    public function getBackgroundImage(): ?GiftCardConfigurationImageInterface
    {
        $images = $this->getImagesByType(GiftCardConfigurationImageInterface::TYPE_BACKGROUND);
        if ($images->isEmpty()) {
            return null;
        }

        return $images->first();
    }

    public function setBackgroundImage(?GiftCardConfigurationImageInterface $image): void
    {
        $actualImage = $this->getBackgroundImage();
        if (null !== $actualImage) {
            $this->removeImage($actualImage);
        }

        if (null === $image) {
            return;
        }

        if (GiftCardConfigurationImageInterface::TYPE_BACKGROUND !== $image->getType()) {
            $image->setType(GiftCardConfigurationImageInterface::TYPE_BACKGROUND);
        }
        $this->addImage($image);
    }

    /**
     * {@inheritdoc}
     */
    public function getChannelConfigurations(): Collection
    {
        return $this->channelConfigurations;
    }

    public function hasChannelConfigurations(): bool
    {
        return !$this->channelConfigurations->isEmpty();
    }

    public function hasChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): bool
    {
        return $this->channelConfigurations->contains($channelConfiguration);
    }

    public function addChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void
    {
        if (!$this->hasChannelConfiguration($channelConfiguration)) {
            $channelConfiguration->setConfiguration($this);
            $this->channelConfigurations->add($channelConfiguration);
        }
    }

    public function removeChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void
    {
        if ($this->hasChannelConfiguration($channelConfiguration)) {
            $channelConfiguration->setConfiguration(null);
            $this->channelConfigurations->removeElement($channelConfiguration);
        }
    }

    public function isDefault(): ?bool
    {
        return $this->default;
    }

    public function setDefault(?bool $default): void
    {
        $this->default = $default;
    }
}
