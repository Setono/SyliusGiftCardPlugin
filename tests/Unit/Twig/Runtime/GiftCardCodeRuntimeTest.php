<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Twig\Runtime;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Generator\BarcodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizerInterface;
use Setono\SyliusGiftCardPlugin\Twig\Runtime\GiftCardCodeRuntime;

final class GiftCardCodeRuntimeTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<GiftCardCodeNormalizerInterface> */
    private ObjectProphecy $normalizer;

    /** @var ObjectProphecy<BarcodeGeneratorInterface> */
    private ObjectProphecy $barcodeGenerator;

    protected function setUp(): void
    {
        $this->normalizer = $this->prophesize(GiftCardCodeNormalizerInterface::class);
        $this->barcodeGenerator = $this->prophesize(BarcodeGeneratorInterface::class);
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

    /**
     * A code is shown grouped but stored ungrouped, so it is the normalized code that has to be encoded:
     * scanning the barcode must hand back the code the shop can look up, separators and all removed
     *
     * @test
     */
    public function it_pictures_the_normalized_code(): void
    {
        $this->normalizer->normalize('SUMM-ER26')->willReturn('SUMMER26')->shouldBeCalledOnce();
        $this->barcodeGenerator->generate('SUMMER26')->willReturn('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=')->shouldBeCalledOnce();

        self::assertSame('data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=', $this->runtime()->barcode('SUMM-ER26'));
    }

    /** @test */
    public function it_pictures_nothing_when_there_is_no_code(): void
    {
        $this->normalizer->normalize(Argument::cetera())->willReturn('');
        $this->barcodeGenerator->generate(Argument::cetera())->shouldNotBeCalled();

        $runtime = $this->runtime();

        self::assertNull($runtime->barcode(null));
        self::assertNull($runtime->barcode('----'));
    }

    private function runtime(): GiftCardCodeRuntime
    {
        return new GiftCardCodeRuntime($this->normalizer->reveal(), $this->barcodeGenerator->reveal());
    }
}
