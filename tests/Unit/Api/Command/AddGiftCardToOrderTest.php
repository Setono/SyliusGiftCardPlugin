<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Command;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AddGiftCardToOrder;
use Setono\SyliusGiftCardPlugin\Api\Command\GiftCardCodeAwareInterface;

class AddGiftCardToOrderTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_initializable(): void
    {
        $command = new AddGiftCardToOrder('order_code');

        $this->assertInstanceOf(GiftCardCodeAwareInterface::class, $command);
    }

    /**
     * @test
     */
    public function it_has_nullable_gift_card_code(): void
    {
        $command = new AddGiftCardToOrder('order_token_vaue');

        $this->assertNull($command->getGiftCardCode());
        $command->setGiftCardCode('gc_code');
        $this->assertEquals('gc_code', $command->getGiftCardCode());
    }
}
