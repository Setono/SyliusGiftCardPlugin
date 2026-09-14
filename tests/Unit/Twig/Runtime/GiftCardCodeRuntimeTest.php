<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;

final class GiftCardCodeRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_groups_the_code_through_the_normalizer(): void
    {
        $normalizer = $this->prophesize(GiftCardCodeNormalizerInterface::class);
        $normalizer->format('SUMMER26', 4, '-')->willReturn('SUMM-ER26')->shouldBeCalledOnce();

        $runtime = new GiftCardCodeRuntime($normalizer->reveal());

        self::assertSame('SUMM-ER26', $runtime->format('SUMMER26'));
    }

    /** @test */
    public function it_passes_the_group_size_and_separator_on(): void
    {
        $normalizer = $this->prophesize(GiftCardCodeNormalizerInterface::class);
        $normalizer->format('ABCDEF', 3, ' ')->willReturn('ABC DEF')->shouldBeCalledOnce();

        $runtime = new GiftCardCodeRuntime($normalizer->reveal());

        self::assertSame('ABC DEF', $runtime->format('ABCDEF', 3, ' '));
    }

    /**
     * A gift card's code is nullable, and a template must not blow up on a card that has none yet
     *
     * @test
     */
    public function it_renders_a_missing_code_as_nothing(): void
    {
        $normalizer = $this->prophesize(GiftCardCodeNormalizerInterface::class);
        $normalizer->format(Argument::cetera())->shouldNotBeCalled();

        $runtime = new GiftCardCodeRuntime($normalizer->reveal());

        self::assertSame('', $runtime->format(null));
    }
}
