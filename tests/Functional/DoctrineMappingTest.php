<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesign;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImage;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignImageInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardDesignTranslation;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransaction;
use Setono\SyliusGiftCardPlugin\Model\GiftCardTransactionInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * CI validates the mapping against the schema; this covers what that cannot see: the physical names applications
 * migrate to, and what the constraints and foreign keys do to the data when rows are inserted or deleted
 */
final class DoctrineMappingTest extends GiftCardFunctionalTestCase
{
    /**
     * Applications generate their migrations from these names and UPGRADE-1.0.md tells them to expect exactly these,
     * whatever naming strategy they configure. The test application configures none, so a column that loses its
     * explicit name shows up here under Doctrine's default name (initialAmount, orderItemUnit_id, ...)
     *
     * @test
     */
    public function it_names_the_tables_and_columns_explicitly(): void
    {
        $expected = [
            GiftCard::class => [
                'table' => 'setono_sylius_gift_card__gift_card',
                'initialAmount' => 'initial_amount',
                'currencyCode' => 'currency_code',
                'deliveryType' => 'delivery_type',
                'customMessage' => 'custom_message',
                'expiresAt' => 'expires_at',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
                'orderItemUnit' => 'order_item_unit_id',
                'design' => 'design_id',
                'customer' => 'customer_id',
                'channel' => 'channel_id',
            ],
            GiftCardTransaction::class => [
                'table' => 'setono_sylius_gift_card__gift_card_transaction',
                'idempotencyKey' => 'idempotency_key',
                'createdAt' => 'created_at',
                'giftCard' => 'gift_card_id',
                'order' => 'order_id',
                'payment' => 'payment_id',
            ],
            GiftCardDesign::class => [
                'table' => 'setono_sylius_gift_card__gift_card_design',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
                'channels' => 'setono_sylius_gift_card__gift_card_design_channels(design_id, channel_id)',
            ],
            GiftCardDesignImage::class => [
                'table' => 'setono_sylius_gift_card__gift_card_design_image',
                'owner' => 'owner_id',
            ],
            GiftCardDesignTranslation::class => [
                'table' => 'setono_sylius_gift_card__gift_card_design_translation',
            ],
            Product::class => [
                'giftCard' => 'gift_card',
            ],
            Order::class => [
                'giftCards' => 'setono_sylius_gift_card__order_gift_cards(order_id, gift_card_id)',
            ],
        ];

        $actual = [];
        foreach ($expected as $class => $names) {
            $metadata = $this->manager->getClassMetadata($class);

            foreach (array_keys($names) as $name) {
                $actual[$class][$name] = match (true) {
                    'table' === $name => $metadata->getTableName(),
                    $metadata->isCollectionValuedAssociation($name) => self::describeJoinTable($metadata->getAssociationMapping($name)),
                    $metadata->hasAssociation($name) => $metadata->getSingleAssociationJoinColumnName($name),
                    default => $metadata->getColumnName($name),
                };
            }
        }

        self::assertSame($expected, $actual);
    }

    /**
     * A new product is not a gift card product until somebody says so, and existing rows get the same default when
     * an application adds the column
     *
     * @test
     */
    public function the_product_gift_card_flag_defaults_to_false_in_the_database(): void
    {
        $mapping = $this->manager->getClassMetadata(Product::class)->getFieldMapping('giftCard');

        self::assertFalse($mapping['options']['default'] ?? null);
    }

    /**
     * The code is what a customer types in to spend the card, so it has to lead to exactly one card
     *
     * @test
     */
    public function it_rejects_two_gift_cards_with_the_same_code(): void
    {
        $this->createGiftCard('DUPLICATECODE');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createGiftCard('DUPLICATECODE');
    }

    /** @test */
    public function it_rejects_two_designs_with_the_same_code(): void
    {
        $this->createDesign('duplicate');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createDesign('duplicate');
    }

    /**
     * The balance operator checks for an existing key before it writes, but two requests can both pass that check.
     * The unique index is what finally stops the second one from moving the balance again
     *
     * @test
     */
    public function it_rejects_a_second_ledger_row_with_the_same_idempotency_key(): void
    {
        $giftCard = $this->createGiftCard('IDEMPOTENT');
        $giftCard->addTransaction($this->createTransaction(-1000, 'redeem:order:1:gift_card:1'));
        $this->manager->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $giftCard->addTransaction($this->createTransaction(-1000, 'redeem:order:1:gift_card:1'));
        $this->manager->flush();
    }

    /**
     * Manual adjustments carry no idempotency key, and a card may have any number of them
     *
     * @test
     */
    public function it_keeps_any_number_of_ledger_rows_without_an_idempotency_key(): void
    {
        $giftCard = $this->createGiftCard('MANYADJUSTMENTS');

        // added to the card only: the ledger is persisted along with its gift card
        $giftCard->addTransaction($this->createTransaction(500, null));
        $giftCard->addTransaction($this->createTransaction(-200, null));
        $giftCard->addTransaction($this->createTransaction(100, null));
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame([500, -200, 100], $this->ledgerOf('MANYADJUSTMENTS'));
    }

    /** @test */
    public function it_deletes_the_ledger_along_with_its_gift_card(): void
    {
        $giftCard = $this->createGiftCard('DELETEDCARD');
        $giftCard->addTransaction($this->createTransaction(5000, 'issue-DELETEDCARD'));
        $this->createGiftCard('KEPTCARD')->addTransaction($this->createTransaction(5000, 'issue-KEPTCARD'));
        $this->manager->flush();

        $this->manager->remove($giftCard);
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame(1, $this->countRows('setono_sylius_gift_card__gift_card_transaction'));
        self::assertSame([5000], $this->ledgerOf('KEPTCARD'));
    }

    /**
     * Shops purge old orders, but a card bought or spent on one is still money the shop owes or has settled. The card
     * bought on the order, the card spent on it and the ledger rows pointing at the order and its payment all stay;
     * only the links to the deleted rows go
     *
     * @test
     */
    public function deleting_an_order_keeps_its_gift_cards_and_their_ledger(): void
    {
        $bought = $this->createGiftCard('BOUGHTONORDER');
        $spent = $this->createGiftCard('SPENTONORDER');
        $order = $this->createOrderBuying($bought);

        $payment = new Payment();
        $payment->setCurrencyCode('USD');
        $payment->setAmount(1000);
        $order->addPayment($payment);
        $order->addGiftCard($spent);

        $redemption = $this->createTransaction(-1000, 'redeem:purged');
        $redemption->setOrder($order);
        $redemption->setPayment($payment);
        $spent->addTransaction($redemption);
        $this->manager->flush();

        $this->manager->remove($order);
        $this->manager->flush();
        $this->manager->clear();

        $reloadedBought = $this->findGiftCard('BOUGHTONORDER');
        self::assertNull($reloadedBought->getOrderItemUnit(), 'the card bought on the order should have outlived it');

        $reloadedSpent = $this->findGiftCard('SPENTONORDER');
        self::assertCount(0, $reloadedSpent->getAppliedOrders());
        self::assertSame(0, $this->countRows('setono_sylius_gift_card__order_gift_cards'));

        $transaction = $reloadedSpent->getTransactions()->first();
        self::assertInstanceOf(GiftCardTransactionInterface::class, $transaction);
        self::assertSame(-1000, $transaction->getAmount(), 'the ledger row should have outlived the order');
        self::assertNull($transaction->getOrder());
        self::assertNull($transaction->getPayment());
    }

    /** @test */
    public function deleting_a_customer_keeps_their_gift_cards(): void
    {
        $customer = new Customer();
        $customer->setEmail('deleted@example.com');
        $this->manager->persist($customer);

        $this->createGiftCard('CUSTOMERSCARD')->setCustomer($customer);
        $this->manager->flush();

        $this->manager->remove($customer);
        $this->manager->flush();
        $this->manager->clear();

        self::assertNull($this->findGiftCard('CUSTOMERSCARD')->getCustomer());
    }

    /**
     * A merchant retiring a design must not take the cards printed with it along. The design's own rows do go: its
     * images, its translations and its channel assignments
     *
     * @test
     */
    public function deleting_a_design_keeps_the_gift_cards_made_with_it(): void
    {
        $design = $this->createDesign('retired');
        $this->createGiftCard('RETIREDDESIGN')->setDesign($design);
        $this->manager->flush();

        $this->manager->remove($design);
        $this->manager->flush();
        $this->manager->clear();

        self::assertNull($this->findGiftCard('RETIREDDESIGN')->getDesign());
        self::assertSame(0, $this->countRows('setono_sylius_gift_card__gift_card_design_image'));
        self::assertSame(0, $this->countRows('setono_sylius_gift_card__gift_card_design_translation'));
        self::assertSame(0, $this->countRows('setono_sylius_gift_card__gift_card_design_channels'));
    }

    /** @test */
    public function it_saves_and_deletes_design_images_through_the_design(): void
    {
        $design = $this->createDesign('images');
        self::assertSame(2, $this->countRows('setono_sylius_gift_card__gift_card_design_image'), 'both sides are saved with the design');

        $back = $design->getBackImage();
        self::assertInstanceOf(GiftCardDesignImageInterface::class, $back);
        $design->removeImage($back);
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame(1, $this->countRows('setono_sylius_gift_card__gift_card_design_image'), 'a removed image is deleted');
        $reloaded = $this->findDesign('images');
        self::assertNotNull($reloaded->getFrontImage());
        self::assertNull($reloaded->getBackImage());
    }

    /** @test */
    public function it_keeps_a_design_name_per_locale(): void
    {
        $design = $this->createDesign('translated');

        // added the way the admin form's translations collection adds one: only the design is persisted
        $translation = new GiftCardDesignTranslation();
        $translation->setLocale('da_DK');
        $translation->setName('Klassisk');
        $design->addTranslation($translation);
        $this->manager->flush();
        $this->manager->clear();

        $reloaded = $this->findDesign('translated');
        self::assertEqualsCanonicalizing(['en_US', 'da_DK'], $reloaded->getTranslations()->getKeys());
        self::assertSame('Translated', $reloaded->getTranslation('en_US')->getName());
        self::assertSame('Klassisk', $reloaded->getTranslation('da_DK')->getName());
    }

    private function createGiftCard(string $code): GiftCardInterface
    {
        /** @var GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createForChannel($this->getChannel());
        $giftCard->setCode($code);
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        $this->manager->persist($giftCard);
        $this->manager->flush();

        return $giftCard;
    }

    private function createTransaction(int $amount, ?string $idempotencyKey): GiftCardTransactionInterface
    {
        /** @var FactoryInterface<GiftCardTransactionInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_transaction');

        $transaction = $factory->createNew();
        $transaction->setAmount($amount);
        $transaction->setType(null === $idempotencyKey ? GiftCardTransactionInterface::TYPE_MANUAL : GiftCardTransactionInterface::TYPE_REDEEM);
        $transaction->setIdempotencyKey($idempotencyKey);

        return $transaction;
    }

    private function createDesign(string $code): GiftCardDesignInterface
    {
        /** @var FactoryInterface<GiftCardDesignInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design');

        /** @var FactoryInterface<GiftCardDesignImageInterface> $imageFactory */
        $imageFactory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card_design_image');

        $design = $factory->createNew();
        $design->setCode($code);
        $design->setName(ucfirst($code));
        $design->addChannel($this->getChannel());

        foreach ([GiftCardDesignImageInterface::TYPE_FRONT, GiftCardDesignImageInterface::TYPE_BACK] as $type) {
            $image = $imageFactory->createNew();
            $image->setType($type);
            // nothing is uploaded, only the row is under test
            $image->setPath(sprintf('te/st/%s-%s.png', $code, $type));
            $design->addImage($image);
        }

        $this->manager->persist($design);
        $this->manager->flush();

        return $design;
    }

    private function createOrderBuying(GiftCardInterface $giftCard): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD');
        $product->setName('Gift card');
        $product->setSlug('gift-card');
        $product->setGiftCard(true);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_VARIANT');
        $variant->setProduct($product);
        $this->manager->persist($variant);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        (new OrderItemUnit($item))->setGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function findGiftCard(string $code): GiftCardInterface
    {
        $giftCard = $this->manager->getRepository(GiftCard::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(GiftCardInterface::class, $giftCard);

        return $giftCard;
    }

    private function findDesign(string $code): GiftCardDesignInterface
    {
        $design = $this->manager->getRepository(GiftCardDesign::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(GiftCardDesignInterface::class, $design);

        return $design;
    }

    /**
     * @return list<int>
     */
    private function ledgerOf(string $code): array
    {
        return array_values(array_map(
            static fn (GiftCardTransactionInterface $transaction): int => $transaction->getAmount(),
            $this->findGiftCard($code)->getTransactions()->toArray(),
        ));
    }

    private function countRows(string $table): int
    {
        $count = $this->manager->getConnection()->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /**
     * @param array<string, mixed>|\ArrayAccess<string, mixed> $mapping
     */
    private static function describeJoinTable(array|\ArrayAccess $mapping): string
    {
        $joinTable = $mapping['joinTable'] ?? null;
        self::assertTrue(is_array($joinTable) || $joinTable instanceof \ArrayAccess);

        $joinColumns = $joinTable['joinColumns'] ?? null;
        $inverseJoinColumns = $joinTable['inverseJoinColumns'] ?? null;
        self::assertTrue(is_array($joinColumns) && is_array($inverseJoinColumns));
        self::assertIsString($joinTable['name'] ?? null);

        $name = static function (mixed $joinColumn): string {
            self::assertTrue(is_array($joinColumn) || $joinColumn instanceof \ArrayAccess);
            self::assertIsString($joinColumn['name'] ?? null);

            return $joinColumn['name'];
        };

        return sprintf(
            '%s(%s, %s)',
            $joinTable['name'],
            implode(', ', array_map($name, $joinColumns)),
            implode(', ', array_map($name, $inverseJoinColumns)),
        );
    }
}
