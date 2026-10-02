<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Grid\Filter;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeNormalizer;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardCodeFilter;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

final class GiftCardCodeFilterTest extends TestCase
{
    use ProphecyTrait;

    private const OPTIONS = ['fields' => ['code']];

    /** @var ObjectProphecy<FilterInterface> */
    private ObjectProphecy $stringFilter;

    private DataSourceInterface $dataSource;

    protected function setUp(): void
    {
        $this->stringFilter = $this->prophesize(FilterInterface::class);
        $this->dataSource = $this->prophesize(DataSourceInterface::class)->reveal();
    }

    /**
     * @test
     *
     * @dataProvider renderingsOfTheCode
     */
    public function it_hands_the_string_filter_the_code_the_way_it_is_stored(string $typed): void
    {
        $this->stringFilter
            ->apply($this->dataSource, 'code', ['type' => 'equal', 'value' => 'ABCDEFGH'], self::OPTIONS)
            ->shouldBeCalledOnce();

        $this->filter()->apply($this->dataSource, 'code', ['type' => 'equal', 'value' => $typed], self::OPTIONS);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function renderingsOfTheCode(): iterable
    {
        yield 'as printed on the card, in lower case' => ['abcd-efgh'];
        yield 'grouped by spaces' => ['ABCD EFGH'];
        yield 'the bare code' => ['ABCDEFGH'];
    }

    /**
     * Sylius' filter also takes the value on its own, compared by the type the grid configures or by "contains"
     *
     * @test
     */
    public function it_normalizes_a_value_given_without_a_type(): void
    {
        $this->stringFilter->apply($this->dataSource, 'code', 'ABCDEFGH', self::OPTIONS)->shouldBeCalledOnce();

        $this->filter()->apply($this->dataSource, 'code', 'abcd-efgh', self::OPTIONS);
    }

    /**
     * A list is separated by commas, which must survive where the dashes do not
     *
     * @test
     */
    public function it_normalizes_each_code_of_a_list_on_its_own(): void
    {
        $this->stringFilter
            ->apply($this->dataSource, 'code', ['type' => 'in', 'value' => 'ABCDEFGH,IJKLMNOP'], self::OPTIONS)
            ->shouldBeCalledOnce();

        $this->filter()->apply($this->dataSource, 'code', ['type' => 'in', 'value' => 'abcd-efgh, IJKL MNOP, -'], self::OPTIONS);
    }

    /**
     * @test
     *
     * @dataProvider valuesWithNothingOfACode
     */
    public function it_does_not_filter_by_a_value_with_nothing_of_a_code_in_it(string $type, string $typed): void
    {
        $this->stringFilter->apply(Argument::cetera())->shouldNotBeCalled();

        $this->filter()->apply($this->dataSource, 'code', ['type' => $type, 'value' => $typed], self::OPTIONS);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function valuesWithNothingOfACode(): iterable
    {
        yield 'separators only' => ['contains', ' - '];
        yield 'characters no code has' => ['equal', 'æøå!'];
        yield 'a list of separators' => ['in', '-, -'];
    }

    /**
     * @test
     *
     * @dataProvider typesThatIgnoreTheValue
     */
    public function it_leaves_the_types_that_ignore_the_value_to_the_string_filter(string $type): void
    {
        $data = ['type' => $type, 'value' => ' - '];
        $this->stringFilter->apply($this->dataSource, 'code', $data, self::OPTIONS)->shouldBeCalledOnce();

        $this->filter()->apply($this->dataSource, 'code', $data, self::OPTIONS);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function typesThatIgnoreTheValue(): iterable
    {
        yield 'empty' => ['empty'];
        yield 'not empty' => ['not_empty'];
    }

    /**
     * A filter the admin has not filled in is Sylius' to ignore, as it does for any other string filter
     *
     * @test
     */
    public function it_passes_a_filter_without_a_value_on_unchanged(): void
    {
        $this->stringFilter->apply($this->dataSource, 'code', ['type' => 'contains'], self::OPTIONS)->shouldBeCalledOnce();

        $this->filter()->apply($this->dataSource, 'code', ['type' => 'contains'], self::OPTIONS);
    }

    private function filter(): GiftCardCodeFilter
    {
        return new GiftCardCodeFilter(new GiftCardCodeNormalizer(), $this->stringFilter->reveal());
    }
}
