<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Order;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Order\GiftCardInformation;

final class GiftCardInformationTest extends TestCase
{
    /**
     * A browser submits every line break of a textarea as CR LF, while its maxlength and the shop's counter count it
     * as one character, so the message is held with line feeds only, and its length is the one the customer was shown
     *
     * @test
     */
    public function it_holds_the_line_breaks_of_the_message_as_line_feeds(): void
    {
        $information = new GiftCardInformation();

        $information->setCustomMessage("Happy\r\nbirthday\rfrom\nus\r\n\r\n");
        self::assertSame("Happy\nbirthday\nfrom\nus\n\n", $information->getCustomMessage());

        $information->setCustomMessage(null);
        self::assertNull($information->getCustomMessage());
    }

    /** @test */
    public function it_holds_the_line_breaks_of_a_message_it_is_constructed_with_as_line_feeds(): void
    {
        self::assertSame("Happy\nbirthday\n", (new GiftCardInformation(5000, "Happy\r\nbirthday\r"))->getCustomMessage());
        self::assertNull((new GiftCardInformation(5000))->getCustomMessage());
    }
}
