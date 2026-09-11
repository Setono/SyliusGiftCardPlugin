<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;

/**
 * A pending gift card exists only because a unit in a cart asked for it, so when that unit goes away the card
 * has to go with it. The card is deleted during the same flush that deletes the unit, which is why the cleanup
 * hooks into onFlush: scheduling another entity for deletion is exactly what that event is for, and neither
 * preRemove nor postRemove may touch the unit of work
 */
final class PendingGiftCardCleanupTest extends GiftCardFunctionalTestCase
{
    private GiftCardRepositoryInterface $giftCardRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GiftCardRepositoryInterface $giftCardRepository */
        $giftCardRepository = self::getContainer()->get('setono_sylius_gift_card.repository.gift_card');
        $this->giftCardRepository = $giftCardRepository;
    }

    /** @test */
    public function it_deletes_a_pending_gift_card_when_its_unit_is_removed(): void
    {
        $order = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP01', enabled: false);

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        $giftCard = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);
        self::assertTrue($giftCard->isPending(), 'precondition: the card is pending');

        $this->manager->remove($unit);
        $this->manager->flush();
        $this->manager->clear();

        self::assertNull(
            $this->giftCardRepository->findOneByCode('PENDINGCLEANUP01')?->getCode(),
            'the pending gift card should have been deleted along with its unit',
        );
    }

    /**
     * Once a card has been paid for it is a liability the shop owes the customer, so it must outlive the cart
     * row it happened to be created from
     *
     * @test
     */
    public function it_keeps_a_gift_card_that_is_no_longer_pending(): void
    {
        $order = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP02', enabled: true);

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);

        $this->manager->remove($unit);
        $this->manager->flush();
        $this->manager->clear();

        $giftCard = $this->giftCardRepository->findOneByCode('PENDINGCLEANUP02');
        self::assertSame('PENDINGCLEANUP02', $giftCard?->getCode(), 'an enabled gift card must survive its unit');
        self::assertNull($giftCard->getOrderItemUnit()?->getId(), 'the association is set null, not cascaded');
    }

    /**
     * The way a cart actually loses a unit: the customer removes the line, Sylius removes the item from the
     * order and Doctrine orphan-removes the units underneath it. No one calls remove() on the unit itself
     *
     * @test
     */
    public function it_deletes_a_pending_gift_card_when_its_item_is_orphan_removed(): void
    {
        $order = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP03', enabled: false);

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);

        $order->removeItem($item);
        $this->manager->flush();
        $this->manager->clear();

        self::assertNull(
            $this->giftCardRepository->findOneByCode('PENDINGCLEANUP03')?->getCode(),
            'the pending gift card should have been deleted along with the orphaned unit',
        );
    }

    private function createOrderWithGiftCardUnit(string $code, bool $enabled): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD_' . $code);
        $product->setName('Gift card');
        $product->setSlug('gift-card-' . strtolower($code));
        $product->setGiftCard(true);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_VARIANT_' . $code);
        $variant->setProduct($product);
        $this->manager->persist($variant);

        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode($code);
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        // Sylius' ToggleableTrait defaults to enabled, so a pending card is disabled explicitly, exactly as
        // the add to cart handler does
        $enabled ? $giftCard->enable() : $giftCard->disable();

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        $unit = new OrderItemUnit($item);
        $unit->setGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $order;
    }
}
