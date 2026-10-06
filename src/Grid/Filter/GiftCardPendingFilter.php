<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\Filter;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * Hides, shows or only shows the pending gift cards: those created when a gift card was put in a cart, still waiting
 * for their order to be paid (the cards GiftCardInterface::getStatus() calls pending). Most carts are never paid for,
 * and a pending card is no liability yet, so the gift card grid hides them unless asked: it gives this filter an empty
 * default value, which Sylius applies to a grid opened without any criteria, and the filter's form offers hiding as its
 * empty choice
 */
final class GiftCardPendingFilter implements FilterInterface
{
    public const NAME = 'setono_sylius_gift_card_pending';

    /**
     * List the pending cards along with the others
     */
    public const SHOW = 'show';

    /**
     * List nothing but the pending cards
     */
    public const ONLY = 'only';

    /**
     * The options are typed as loosely as sylius/grid-bundle before 1.15 types them, which the plugin still supports
     *
     * @param mixed $data
     * @param array<mixed, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (self::SHOW === $data) {
            return;
        }

        $pending = self::pendingCondition();

        // Anything but the two choices hides them, like the empty choice does, so a value the form does not offer
        // cannot reveal them either
        $dataSource->restrict(self::ONLY === $data ? $pending : sprintf('NOT (%s)', $pending));
    }

    /**
     * What GiftCard::getStatus() calls pending, in DQL: GiftCard::isPending() (disabled, on a unit, no ledger row yet),
     * on an order that is not cancelled. The card of an order cancelled before it was paid waits for nothing.
     *
     * Sylius' expression builder can neither say that a collection is empty nor reach the order without joining it to
     * the grid's query (an inner join, which would drop the cards issued in the admin), so the condition is written out.
     * It goes on the alias GiftCardRepository::createListQueryBuilder() gives the grid's gift cards, o, which is the one
     * Sylius' driver gives the root of any grid too; sylius/grid-bundle only hands a filter the query builder from 1.13
     * on. Doctrine turns IS EMPTY and the correlated subquery into SQL any database runs
     */
    private static function pendingCondition(): string
    {
        return sprintf(
            'o.enabled = false AND o.orderItemUnit IS NOT NULL AND o.transactions IS EMPTY AND NOT EXISTS (%s)',
            sprintf(
                "SELECT 1 FROM %s pendingFilterCard JOIN pendingFilterCard.orderItemUnit pendingFilterUnit JOIN pendingFilterUnit.orderItem pendingFilterItem JOIN pendingFilterItem.order pendingFilterOrder WHERE pendingFilterCard = o AND pendingFilterOrder.state = '%s'",
                GiftCardInterface::class,
                OrderInterface::STATE_CANCELLED,
            ),
        );
    }
}
