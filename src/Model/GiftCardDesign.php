<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;
use Sylius\Resource\Model\TranslatableTrait;

class GiftCardDesign implements GiftCardDesignInterface, \Stringable
{
    use TimestampableTrait;
    use ToggleableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    protected ?int $id = null;

    protected ?string $code = null;

    protected int $position = 0;

    /** @var Collection<array-key, ImageInterface> */
    protected Collection $images;

    /** @var Collection<array-key, BaseChannelInterface> */
    protected Collection $channels;

    public function __construct()
    {
        $this->initializeTranslationsCollection();

        $this->images = new ArrayCollection();
        $this->channels = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) ($this->getName() ?? $this->code);
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

    public function getName(): ?string
    {
        return $this->getTranslation()->getName();
    }

    public function setName(?string $name): void
    {
        $this->getTranslation()->setName($name);
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getImagesByType(string $type): Collection
    {
        return $this->images->filter(static fn (ImageInterface $image): bool => $image->getType() === $type);
    }

    public function hasImages(): bool
    {
        return !$this->images->isEmpty();
    }

    public function hasImage(ImageInterface $image): bool
    {
        return $this->images->contains($image);
    }

    public function addImage(ImageInterface $image): void
    {
        $image->setOwner($this);
        $this->images->add($image);
    }

    public function removeImage(ImageInterface $image): void
    {
        if ($this->hasImage($image)) {
            $image->setOwner(null);
            $this->images->removeElement($image);
        }
    }

    public function getFrontImage(): ?GiftCardDesignImageInterface
    {
        return $this->getImageByType(GiftCardDesignImageInterface::TYPE_FRONT);
    }

    public function getBackImage(): ?GiftCardDesignImageInterface
    {
        return $this->getImageByType(GiftCardDesignImageInterface::TYPE_BACK);
    }

    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function hasChannel(BaseChannelInterface $channel): bool
    {
        return $this->channels->contains($channel);
    }

    public function addChannel(BaseChannelInterface $channel): void
    {
        if (!$this->hasChannel($channel)) {
            $this->channels->add($channel);
        }
    }

    public function removeChannel(BaseChannelInterface $channel): void
    {
        if ($this->hasChannel($channel)) {
            $this->channels->removeElement($channel);
        }
    }

    /**
     * @return TranslationInterface&GiftCardDesignTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        /** @var TranslationInterface&GiftCardDesignTranslationInterface $translation */
        $translation = $this->doGetTranslation($locale);

        return $translation;
    }

    protected function createTranslation(): GiftCardDesignTranslationInterface
    {
        return new GiftCardDesignTranslation();
    }

    private function getImageByType(string $type): ?GiftCardDesignImageInterface
    {
        $image = $this->getImagesByType($type)->first();

        return $image instanceof GiftCardDesignImageInterface ? $image : null;
    }
}
