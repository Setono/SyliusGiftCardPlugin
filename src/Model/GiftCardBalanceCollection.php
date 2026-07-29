<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Model;

use Countable;
use Iterator;
use OutOfBoundsException;
use Webmozart\Assert\Assert;

/**
 * @implements Iterator<array-key, GiftCardBalance>
 */
final class GiftCardBalanceCollection implements Countable, Iterator
{
    /** @var GiftCardBalance[] */
    private array $collection = [];

    public function addGiftCard(GiftCardInterface $giftCard): void
    {
        $currencyCode = $giftCard->getCurrencyCode();
        Assert::notNull($currencyCode);

        if (!isset($this->collection[$currencyCode])) {
            $this->collection[$currencyCode] = new GiftCardBalance($currencyCode);
        }

        $this->collection[$currencyCode]->add($giftCard->getAmount());
    }

    /**
     * @param iterable<GiftCardInterface> $giftCards
     */
    public static function createFromGiftCards(iterable $giftCards): self
    {
        $collection = new self();
        foreach ($giftCards as $giftCard) {
            $collection->addGiftCard($giftCard);
        }

        return $collection;
    }

    #[\Override]
    public function count(): int
    {
        return count($this->collection);
    }

    #[\Override]
    public function current(): GiftCardBalance
    {
        $cur = current($this->collection);
        if (false === $cur) {
            throw new OutOfBoundsException('Do not call current on an uninitialized collection');
        }

        return $cur;
    }

    #[\Override]
    public function next(): void
    {
        next($this->collection);
    }

    #[\Override]
    public function key(): ?string
    {
        $k = (string) key($this->collection);

        return '' === $k ? null : $k;
    }

    #[\Override]
    public function valid(): bool
    {
        return key($this->collection) !== null;
    }

    #[\Override]
    public function rewind(): void
    {
        reset($this->collection);
    }
}
