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

    protected ?int $id = null;

    protected ?string $code = null;

    /**
     * @var ImageInterface[]|Collection
     * @psalm-var Collection<array-key, ImageInterface>
     */
    protected Collection $images;

    /**
     * @var GiftCardChannelConfigurationInterface[]|Collection
     * @psalm-var Collection<array-key, GiftCardChannelConfigurationInterface>
     */
    protected Collection $channelConfigurations;

    protected bool $default = false;

    protected ?string $defaultValidityPeriod = null;

    protected ?string $pageSize = null;

    protected ?string $orientation = null;

    protected ?string $template = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->channelConfigurations = new ArrayCollection();
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getCode(): ?string
    {
        return $this->code;
    }

    #[\Override]
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    #[\Override]
    public function getImages(): Collection
    {
        return $this->images;
    }

    #[\Override]
    public function getImagesByType(string $type): Collection
    {
        return $this->images->filter(function (ImageInterface $image) use ($type): bool {
            return $image->getType() === $type;
        });
    }

    #[\Override]
    public function hasImages(): bool
    {
        return !$this->getImages()->isEmpty();
    }

    #[\Override]
    public function hasImage(ImageInterface $image): bool
    {
        return $this->getImages()->contains($image);
    }

    #[\Override]
    public function addImage(ImageInterface $image): void
    {
        if (!$this->hasImage($image)) {
            $image->setOwner($this);
            $this->images->add($image);
        }
    }

    #[\Override]
    public function removeImage(ImageInterface $image): void
    {
        if ($this->hasImage($image)) {
            $image->setOwner(null);
            $this->images->removeElement($image);
        }
    }

    #[\Override]
    public function getBackgroundImage(): ?GiftCardConfigurationImageInterface
    {
        $images = $this->getImagesByType(GiftCardConfigurationImageInterface::TYPE_BACKGROUND);
        if ($images->isEmpty()) {
            return null;
        }

        /** @var GiftCardConfigurationImageInterface $image */
        $image = $images->first();

        return $image;
    }

    #[\Override]
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

    #[\Override]
    public function getChannelConfigurations(): Collection
    {
        return $this->channelConfigurations;
    }

    #[\Override]
    public function hasChannelConfigurations(): bool
    {
        return !$this->channelConfigurations->isEmpty();
    }

    #[\Override]
    public function hasChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): bool
    {
        return $this->channelConfigurations->contains($channelConfiguration);
    }

    #[\Override]
    public function addChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void
    {
        if (!$this->hasChannelConfiguration($channelConfiguration)) {
            $channelConfiguration->setConfiguration($this);
            $this->channelConfigurations->add($channelConfiguration);
        }
    }

    #[\Override]
    public function removeChannelConfiguration(GiftCardChannelConfigurationInterface $channelConfiguration): void
    {
        if ($this->hasChannelConfiguration($channelConfiguration)) {
            $channelConfiguration->setConfiguration(null);
            $this->channelConfigurations->removeElement($channelConfiguration);
        }
    }

    #[\Override]
    public function isDefault(): bool
    {
        return $this->default;
    }

    #[\Override]
    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }

    #[\Override]
    public function getDefaultValidityPeriod(): ?string
    {
        return $this->defaultValidityPeriod;
    }

    #[\Override]
    public function setDefaultValidityPeriod(?string $defaultValidityPeriod): void
    {
        $this->defaultValidityPeriod = $defaultValidityPeriod;
    }

    #[\Override]
    public function getPageSize(): ?string
    {
        return $this->pageSize;
    }

    #[\Override]
    public function setPageSize(?string $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    #[\Override]
    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    #[\Override]
    public function setOrientation(?string $orientation): void
    {
        $this->orientation = $orientation;
    }

    #[\Override]
    public function getTemplate(): ?string
    {
        return $this->template;
    }

    #[\Override]
    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }
}
