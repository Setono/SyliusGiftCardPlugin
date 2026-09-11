<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Sylius\Component\Resource\Model\AbstractTranslation;

class GiftCardDesignTranslation extends AbstractTranslation implements GiftCardDesignTranslationInterface
{
    protected ?int $id = null;

    protected ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
