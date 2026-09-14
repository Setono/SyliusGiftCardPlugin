<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The physical column name of a field mapped without an explicit column is decided by the *application's*
 * Doctrine naming strategy, so two host applications would end up with different schemas for the same plugin and
 * the SQL in UPGRADE-1.0.md would only be right for some of them.
 *
 * The test application deliberately configures no naming strategy (like Sylius-Standard), which makes Doctrine fall
 * back to the field name verbatim. These assertions therefore only hold while every column is named explicitly.
 */
final class DoctrineColumnNamingTest extends KernelTestCase
{
    /**
     * @test
     *
     * @dataProvider giftCardColumns
     */
    public function it_maps_gift_card_fields_to_explicitly_named_columns(string $field, string $column): void
    {
        self::assertColumnName('setono_sylius_gift_card.model.gift_card.class', $field, $column);
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function giftCardColumns(): iterable
    {
        yield ['code', 'code'];
        yield ['enabled', 'enabled'];
        yield ['amount', 'amount'];
        yield ['initialAmount', 'initial_amount'];
        yield ['currencyCode', 'currency_code'];
        yield ['deliveryType', 'delivery_type'];
        yield ['customMessage', 'custom_message'];
        yield ['expiresAt', 'expires_at'];
        yield ['version', 'version'];
        yield ['createdAt', 'created_at'];
        yield ['updatedAt', 'updated_at'];
    }

    /**
     * @test
     *
     * @dataProvider giftCardTransactionColumns
     */
    public function it_maps_gift_card_transaction_fields_to_explicitly_named_columns(string $field, string $column): void
    {
        self::assertColumnName('setono_sylius_gift_card.model.gift_card_transaction.class', $field, $column);
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function giftCardTransactionColumns(): iterable
    {
        yield ['amount', 'amount'];
        yield ['type', 'type'];
        yield ['reason', 'reason'];
        yield ['idempotencyKey', 'idempotency_key'];
        yield ['createdAt', 'created_at'];
    }

    /**
     * @test
     *
     * @dataProvider giftCardDesignColumns
     */
    public function it_maps_gift_card_design_fields_to_explicitly_named_columns(string $field, string $column): void
    {
        self::assertColumnName('setono_sylius_gift_card.model.gift_card_design.class', $field, $column);
    }

    /**
     * @return iterable<array-key, array{string, string}>
     */
    public static function giftCardDesignColumns(): iterable
    {
        yield ['code', 'code'];
        yield ['position', 'position'];
        yield ['enabled', 'enabled'];
        yield ['createdAt', 'created_at'];
        yield ['updatedAt', 'updated_at'];
    }

    /** @test */
    public function it_maps_the_gift_card_design_translation_name_to_an_explicitly_named_column(): void
    {
        self::assertColumnName('setono_sylius_gift_card.model.gift_card_design_translation.class', 'name', 'name');
    }

    /**
     * The flag added to the host application's product entity by ProductTrait: it is mapped by the trait's own
     * attribute/annotation, so it has to carry the column name itself.
     *
     * @test
     */
    public function it_maps_the_product_gift_card_flag_to_an_explicitly_named_column(): void
    {
        self::assertColumnName('sylius.model.product.class', 'giftCard', 'gift_card');
    }

    private static function assertColumnName(string $classParameter, string $field, string $expectedColumn): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $class = $container->getParameter($classParameter);
        self::assertIsString($class);

        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('The parameter "%s" does not resolve to a class: %s', $classParameter, $class));
        }

        /** @var EntityManagerInterface $manager */
        $manager = $container->get('doctrine.orm.entity_manager');

        self::assertSame($expectedColumn, $manager->getClassMetadata($class)->getColumnName($field));
    }
}
