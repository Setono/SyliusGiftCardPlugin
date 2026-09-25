<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Grid\Filter;

use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filter\StringFilter;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Sylius' string filter, fed the code the way it is stored. Codes are printed grouped (ABCD-EFGH-JKMN-PQRS) on the
 * card, in the emails and on the admin's pages, and that is how a customer reads one out to support, while the stored
 * code has no separators, so the typed value is normalized before it is compared, like the cart does with a code
 */
final class GiftCardCodeFilter implements FilterInterface
{
    public const NAME = 'setono_sylius_gift_card_code';

    public function __construct(
        private readonly GiftCardCodeNormalizerInterface $codeNormalizer,
        private readonly FilterInterface $stringFilter,
    ) {
    }

    /**
     * The options are typed as loosely as sylius/grid-bundle before 1.15 types them, which the plugin still supports
     *
     * @param mixed $data
     * @param array<mixed, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        // Handed on as they came, typed the way the string filter of any supported version accepts them
        /** @var array<string, mixed> $stringFilterOptions */
        $stringFilterOptions = $options;

        $value = is_array($data) ? $data['value'] ?? null : $data;
        $type = (is_array($data) ? $data['type'] ?? null : null) ?? $options['type'] ?? StringFilter::TYPE_CONTAINS;

        // "empty" and "not empty" ignore the value, and there is nothing to normalize in a value that is not there
        if (!is_string($value) || in_array($type, [StringFilter::TYPE_EMPTY, StringFilter::TYPE_NOT_EMPTY], true)) {
            $this->stringFilter->apply($dataSource, $name, $data, $stringFilterOptions);

            return;
        }

        $value = in_array($type, [StringFilter::TYPE_IN, StringFilter::TYPE_NOT_IN], true)
            ? $this->normalizeList($value)
            : $this->codeNormalizer->normalize($value);

        // Nothing typed, or nothing that can be part of a code (only dashes and spaces, say): that filters by nothing,
        // like an empty filter does, rather than searching for an empty code
        if ('' === $value) {
            return;
        }

        if (is_array($data)) {
            $data['value'] = $value;
        } else {
            $data = $value;
        }

        $this->stringFilter->apply($dataSource, $name, $data, $stringFilterOptions);
    }

    /**
     * "in" and "not in" take a comma separated list of codes, and normalizing the list as a whole would strip the
     * commas along with the dashes and run the codes together
     */
    private function normalizeList(string $value): string
    {
        $codes = array_map($this->codeNormalizer->normalize(...), explode(',', $value));

        return implode(',', array_filter($codes, static fn (string $code): bool => '' !== $code));
    }
}
