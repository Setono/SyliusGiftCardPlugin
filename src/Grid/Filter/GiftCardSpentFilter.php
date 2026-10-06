<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filter\BooleanFilter;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Whether nothing is left on the card. It looks at the balance alone, so it finds a disabled card with nothing left
 * too, which the status column shows as disabled
 */
final class GiftCardSpentFilter implements FilterInterface
{
    public const NAME = 'setono_sylius_gift_card_spent';

    /**
     * The options are typed as loosely as sylius/grid-bundle before 1.15 types them, which the plugin still supports
     *
     * @param mixed $data
     * @param array<mixed, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (BooleanFilter::TRUE !== $data && BooleanFilter::FALSE !== $data) {
            return;
        }

        $expressionBuilder = $dataSource->getExpressionBuilder();

        // The exact opposite of what a card needs to be usable, a balance above 0
        $dataSource->restrict(BooleanFilter::TRUE === $data
            ? $expressionBuilder->lessThanOrEqual('amount', 0)
            : $expressionBuilder->greaterThan('amount', 0));
    }
}
