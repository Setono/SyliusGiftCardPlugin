<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ProductTrait
{
    /**
     * The column is named explicitly: an implicit name is resolved by the *application's* naming strategy, so two
     * host applications would end up with different physical schemas for the same plugin.
     *
     * @ORM\Column(name="gift_card", type="boolean", options={"default": false})
     */
    #[ORM\Column(name: 'gift_card', type: 'boolean', options: ['default' => false])]
    protected bool $giftCard = false;

    public function isGiftCard(): bool
    {
        return $this->giftCard;
    }

    public function setGiftCard(bool $giftCard): void
    {
        $this->giftCard = $giftCard;
    }
}
