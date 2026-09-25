<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use function assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Order;

/**
 * @mixin Order
 */
trait OrderTrait
{
    /**
     * @var Collection|GiftCardInterface[]
     *
     * @ORM\ManyToMany(targetEntity="Setono\SyliusGiftCardPlugin\Model\GiftCardInterface", inversedBy="appliedOrders")
     *
     * @ORM\JoinTable(name="setono_sylius_gift_card__order_gift_cards",
     *     joinColumns={@ORM\JoinColumn(name="order_id", referencedColumnName="id", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="gift_card_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    #[ORM\ManyToMany(targetEntity: GiftCardInterface::class, inversedBy: 'appliedOrders')]
    #[ORM\JoinTable(name: 'setono_sylius_gift_card__order_gift_cards')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'gift_card_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected Collection $giftCards;

    public function __construct()
    {
        $this->giftCards = new ArrayCollection();
    }

    public function getGiftCards(): Collection
    {
        return $this->giftCards;
    }

    public function hasGiftCards(): bool
    {
        return !$this->giftCards->isEmpty();
    }

    public function addGiftCard(GiftCardInterface $giftCard): void
    {
        assert($this instanceof OrderInterface);

        if (!$this->hasGiftCard($giftCard)) {
            $this->giftCards->add($giftCard);
            $giftCard->addAppliedOrder($this);
        }
    }

    public function removeGiftCard(GiftCardInterface $giftCard): void
    {
        assert($this instanceof OrderInterface);

        if ($this->hasGiftCard($giftCard)) {
            $this->giftCards->removeElement($giftCard);
            $giftCard->removeAppliedOrder($this);
        }
    }

    public function hasGiftCard(GiftCardInterface $giftCard): bool
    {
        return $this->giftCards->contains($giftCard);
    }

    /**
     * What promotions look at: the items total without the gift cards being bought. A gift card is worth the amount
     * the customer chose, so its line takes no share of a discount (see GiftCardExcludingMinimumPriceDistributor), and
     * a percentage of the order is only a percentage of what can be discounted. For the same reason buying a gift card
     * does not count towards a promotion's "item total" rule
     */
    public function getPromotionSubjectTotal(): int
    {
        return parent::getPromotionSubjectTotal() - $this->getGiftCardItemsTotal(false);
    }

    /**
     * The items total a promotion that does not apply to already discounted items looks at, without the gift cards
     * being bought, for the reason given on getPromotionSubjectTotal()
     */
    public function getNonDiscountedItemsTotal(): int
    {
        return parent::getNonDiscountedItemsTotal() - $this->getGiftCardItemsTotal(true);
    }

    /**
     * @param bool $nonDiscountedOnly whether to leave out the gift card lines Sylius itself leaves out of the non
     *                                discounted items total, i.e. those whose variant has a catalog promotion applied
     */
    private function getGiftCardItemsTotal(bool $nonDiscountedOnly): int
    {
        $channel = $this->getChannel();

        $total = 0;
        foreach ($this->getItems() as $item) {
            $product = $item->getProduct();
            if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
                continue;
            }

            $variant = $item->getVariant();
            if ($nonDiscountedOnly && null !== $channel && null !== $variant && !$variant->getAppliedPromotionsForChannel($channel)->isEmpty()) {
                continue;
            }

            $total += $item->getTotal();
        }

        return $total;
    }
}
