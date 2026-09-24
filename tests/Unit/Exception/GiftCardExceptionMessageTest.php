<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Exception\GiftCardCurrencyMismatchException;
use Setono\SyliusGiftCardPlugin\Exception\InsufficientGiftCardBalanceException;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

/**
 * An uncaught exception ends up in the log and in error trackers, so its message names a gift card by its id and
 * never by its code, which is a bearer token
 */
final class GiftCardExceptionMessageTest extends TestCase
{
    use ProphecyTrait;

    private const CODE = 'ABCDEFGHJKMNPQRS';

    /**
     * @test
     *
     * @dataProvider exceptions
     *
     * @param \Closure(GiftCardInterface): \Throwable $throw
     */
    public function it_names_the_gift_card_by_its_id_rather_than_its_code(\Closure $throw): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getId()->willReturn(42);
        $giftCard->getCode()->willReturn(self::CODE);
        $giftCard->getAmount()->willReturn(500);
        $giftCard->getCurrencyCode()->willReturn('EUR');

        $message = $throw($giftCard->reveal())->getMessage();

        self::assertStringContainsString('id 42', $message);
        self::assertStringNotContainsString(self::CODE, $message);
    }

    /**
     * @return iterable<string, array{\Closure(GiftCardInterface): \Throwable}>
     */
    public static function exceptions(): iterable
    {
        yield 'insufficient balance' => [static fn (GiftCardInterface $giftCard): \Throwable => new InsufficientGiftCardBalanceException($giftCard, 1000)];
        yield 'currency mismatch' => [static fn (GiftCardInterface $giftCard): \Throwable => new GiftCardCurrencyMismatchException($giftCard, 'USD')];
    }
}
