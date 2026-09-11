<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Core\Model\ImagesAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface GiftCardDesignInterface extends
    ResourceInterface,
    CodeAwareInterface,
    ToggleableInterface,
    TimestampableInterface,
    TranslatableInterface,
    ImagesAwareInterface,
    ChannelsAwareInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getPosition(): int;

    public function setPosition(int $position): void;

    /**
     * The image shown to the customer in the design picker, in the live preview and on the front page of the PDF
     */
    public function getFrontImage(): ?GiftCardDesignImageInterface;

    /**
     * The optional back side used on the second page of the PDF. When null, a default back is rendered
     */
    public function getBackImage(): ?GiftCardDesignImageInterface;

    /**
     * @return TranslationInterface&GiftCardDesignTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface;
}
