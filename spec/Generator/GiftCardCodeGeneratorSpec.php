<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Generator;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardCodeGeneratorSpec extends ObjectBehavior
{
    public function let(GiftCardRepositoryInterface $giftCardRepository): void
    {
        $this->beConstructedWith($giftCardRepository);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardCodeGenerator::class);
    }

    public function it_implements_gift_card_code_generator_interface(): void
    {
        $this->shouldHaveType(GiftCardCodeGeneratorInterface::class);
    }

    public function it_generates(GiftCardRepositoryInterface $giftCardRepository): void
    {
        $giftCardRepository->findOneByCode(Argument::type('string'))->willReturn(null);

        $this->generate()->shouldBeString();
    }
}
