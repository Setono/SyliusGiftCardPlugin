<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Generator;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGenerator;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardCodeGeneratorSpec extends ObjectBehavior
{
    function let(RepositoryInterface $giftCardCodeRepository): void
    {
        $this->beConstructedWith($giftCardCodeRepository);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardCodeGenerator::class);
    }

    function it_implements_gift_card_code_generator_interface(): void
    {
        $this->shouldHaveType(GiftCardCodeGeneratorInterface::class);
    }

    function it_generates(RepositoryInterface $giftCardCodeRepository): void
    {
        $giftCardCodeRepository->findOneBy(Argument::type('array'))->willReturn(null);

        $this->generate()->shouldBeString();
    }
}
