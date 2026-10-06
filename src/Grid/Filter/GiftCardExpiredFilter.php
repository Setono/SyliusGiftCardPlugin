<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filter\BooleanFilter;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Whether the card's expiry date has passed, the way GiftCardInterface::isExpired() judges it: a card without an expiry
 * date never expires. It looks at the date alone, so it finds a disabled or spent card that has expired too, which the
 * status column shows by its more telling status
 */
final class GiftCardExpiredFilter implements FilterInterface
{
    public const NAME = 'setono_sylius_gift_card_expired';

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

        $now = new \DateTimeImmutable();
        $expressionBuilder = $dataSource->getExpressionBuilder();

        // A card expires once now is past its expiry date, so a card expiring this very second is not expired yet
        $dataSource->restrict(BooleanFilter::TRUE === $data
            ? $expressionBuilder->lessThan('expiresAt', $now)
            : $expressionBuilder->orX($expressionBuilder->isNull('expiresAt'), $expressionBuilder->greaterThanOrEqual('expiresAt', $now)));
    }
}
