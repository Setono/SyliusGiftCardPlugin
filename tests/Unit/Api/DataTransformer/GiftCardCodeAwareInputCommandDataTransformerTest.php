<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\DataTransformer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Api\Command\AddGiftCardToOrder;
use Setono\SyliusGiftCardPlugin\Api\Command\GiftCardCodeAwareInterface;
use Setono\SyliusGiftCardPlugin\Api\DataTransformer\GiftCardCodeAwareInputCommandDataTransformer;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;

final class GiftCardCodeAwareInputCommandDataTransformerTest extends TestCase
{
    /**
     * @test
     */
    public function it_supports_gift_card_code_aware_interface(): void
    {
        $dataTransformer = new GiftCardCodeAwareInputCommandDataTransformer();

        self::assertFalse($dataTransformer->supportsTransformation('anything'));
        self::assertTrue($dataTransformer->supportsTransformation(new AddGiftCardToOrder('token_value')));
    }

    /**
     * @test
     */
    public function it_adds_gift_card_code_to_object(): void
    {
        $dataTransformer = new GiftCardCodeAwareInputCommandDataTransformer();

        $addGiftCardToOrder = new AddGiftCardToOrder('token_value');
        $giftCard = new GiftCard();
        $giftCard->setCode('gc_code');

        $transformedCommand = $dataTransformer->transform(
            $addGiftCardToOrder,
            GiftCardCodeAwareInterface::class,
            ['object_to_populate' => $giftCard]
        );
        self::assertEquals('gc_code', $transformedCommand->getGiftCardCode());
    }
}
