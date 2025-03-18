<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardCodeGeneratorTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_generates_gift_card_code(): void
    {
        $codeLength = 10;
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);

        $giftCardCodeGenerator = new GiftCardCodeGenerator($giftCardRepository->reveal(), $codeLength);

        $code = $giftCardCodeGenerator->generate();
        $this->assertEquals($codeLength, \strlen($code));
    }
}
