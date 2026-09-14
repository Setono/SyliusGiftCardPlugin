<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;

final class GiftCardCodeRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardCodeNormalizerInterface> */
    private ObjectProphecy $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = $this->prophesize(GiftCardCodeNormalizerInterface::class);
    }

    /** @test */
    public function it_groups_the_code_through_the_normalizer(): void
    {
        $this->normalizer->format('SUMMER26', 4, '-')->willReturn('SUMM-ER26')->shouldBeCalledOnce();

        self::assertSame('SUMM-ER26', $this->runtime()->format('SUMMER26'));
    }

    /** @test */
    public function it_passes_the_group_size_and_separator_on(): void
    {
        $this->normalizer->format('ABCDEF', 3, ' ')->willReturn('ABC DEF')->shouldBeCalledOnce();

        self::assertSame('ABC DEF', $this->runtime()->format('ABCDEF', 3, ' '));
    }

    /**
     * A gift card's code is nullable, and a template must not blow up on a card that has none yet
     *
     * @test
     */
    public function it_renders_a_missing_code_as_nothing(): void
    {
        $this->normalizer->format(Argument::cetera())->shouldNotBeCalled();

        self::assertSame('', $this->runtime()->format(null));
    }

    private function runtime(): GiftCardCodeRuntime
    {
        return new GiftCardCodeRuntime($this->normalizer->reveal());
    }
}
