<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Remover\ExpiredCartsRemoverInterface;

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

    /**
     * Lowering the quantity in the cart removes units from the line rather than the line itself, so each removed
     * unit takes its own card along and the units that stay keep theirs
     *
     * @test
     */
    public function it_deletes_the_pending_gift_cards_of_the_units_a_quantity_decrease_removes(): void
    {
        $order = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP04', enabled: false, extraCodes: ['PENDINGCLEANUP05', 'PENDINGCLEANUP06']);

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);
        self::assertSame(3, $item->getQuantity(), 'precondition: three units, each with its card');

        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = self::getContainer()->get('sylius.order_item_quantity_modifier');
        $quantityModifier->modify($item, 1);
        $this->manager->flush();

        $keptUnit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $keptUnit);
        $keptCode = $keptUnit->getGiftCard()?->getCode();
        self::assertNotNull($keptCode);
        $this->manager->clear();

        $remaining = [];
        foreach ($this->giftCardRepository->findAll() as $giftCard) {
            self::assertInstanceOf(GiftCardInterface::class, $giftCard);
            $remaining[] = $giftCard->getCode();
        }

        self::assertSame([$keptCode], $remaining, 'only the card of the unit left in the cart should remain');
    }

    /**
     * Sylius prunes carts nobody touched for a while, which takes the pending cards in them along. The cards of an
     * order that was placed are left alone, since that order is no cart
     *
     * @test
     */
    public function it_deletes_the_pending_gift_cards_of_an_expired_cart(): void
    {
        $expiredCart = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP07', enabled: false);
        $placedOrder = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP08', enabled: true);
        $placedOrder->setState(OrderInterface::STATE_NEW);
        $this->manager->flush();

        // last touched long enough ago to have expired, written past the timestampable listener
        $this->manager->getConnection()->executeStatement(
            'UPDATE sylius_order SET updated_at = :updatedAt',
            ['updatedAt' => (new \DateTimeImmutable('-1 year'))->format('Y-m-d H:i:s')],
        );
        $expiredCartId = $expiredCart->getId();
        $this->manager->clear();

        /** @var ExpiredCartsRemoverInterface $expiredCartsRemover */
        $expiredCartsRemover = self::getContainer()->get('sylius.expired_carts_remover');
        $expiredCartsRemover->remove();
        $this->manager->clear();

        self::assertNull($this->manager->find(Order::class, $expiredCartId), 'precondition: the cart should have been removed');
        self::assertNull(
            $this->giftCardRepository->findOneByCode('PENDINGCLEANUP07')?->getCode(),
            'the pending gift card should have been deleted along with the expired cart',
        );
        self::assertSame('PENDINGCLEANUP08', $this->giftCardRepository->findOneByCode('PENDINGCLEANUP08')?->getCode());
    }

    /**
     * A card that was paid for and then disabled, because its order was cancelled, is disabled like a pending card.
     * Its ledger tells it apart: the shop took money for it, so it stays on record
     *
     * @test
     */
    public function it_keeps_a_disabled_gift_card_that_was_paid_for(): void
    {
        $order = $this->createOrderWithGiftCardUnit('PENDINGCLEANUP09', enabled: false);

        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $item);
        $unit = $item->getUnits()->first();
        self::assertInstanceOf(OrderItemUnit::class, $unit);
        $giftCard = $unit->getGiftCard();
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        /** @var GiftCardBalanceOperatorInterface $balanceOperator */
        $balanceOperator = self::getContainer()->get(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard);
        $this->manager->flush();
        self::assertFalse($giftCard->isPending(), 'precondition: a card with a ledger is not pending');

        $this->manager->remove($unit);
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame('PENDINGCLEANUP09', $this->giftCardRepository->findOneByCode('PENDINGCLEANUP09')?->getCode());
    }

    /**
     * @param list<string> $extraCodes the codes of the pending cards of further units on the same line
     */
    private function createOrderWithGiftCardUnit(string $code, bool $enabled, array $extraCodes = []): Order
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

        foreach ($extraCodes as $extraCode) {
            $extraGiftCard = $factory->createForChannel($this->getChannel());
            $extraGiftCard->setCode($extraCode);
            $extraGiftCard->setInitialAmount(5000);
            $extraGiftCard->setAmount(5000);
            $extraGiftCard->disable();
            $this->manager->persist($extraGiftCard);

            (new OrderItemUnit($item))->setGiftCard($extraGiftCard);
        }

        $this->manager->flush();

        return $order;
    }
}
