<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Hides, shows or only shows the pending gift cards: those created when a gift card was put in a cart, still waiting
 * for their order to be paid (GiftCardInterface::isPending()). Most carts are never paid for, and a pending card is
 * no liability yet, so the gift card grid hides them unless asked: it gives this filter an empty default value, which
 * Sylius applies to a grid opened without any criteria, and the filter's form offers hiding as its empty choice
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

        // GiftCard::isPending() in DQL. Sylius' expression builder cannot say that a collection is empty, so it is
        // written out, on the alias GiftCardRepository::createListQueryBuilder() gives the grid's gift cards (the one
        // Sylius' driver gives the root of any grid, too). Doctrine turns IS EMPTY into a subquery any database runs
        $pending = 'o.enabled = false AND o.orderItemUnit IS NOT NULL AND o.transactions IS EMPTY';

        // Anything but the two choices hides them, like the empty choice does, so a value the form does not offer
        // cannot reveal them either
        $dataSource->restrict(self::ONLY === $data ? $pending : sprintf('NOT (%s)', $pending));
    }
}
