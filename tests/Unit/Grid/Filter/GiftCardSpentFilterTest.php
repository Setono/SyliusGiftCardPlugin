<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Grid\Filter;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardSpentFilter;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Data\ExpressionBuilderInterface;

final class GiftCardSpentFilterTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<DataSourceInterface> */
    private ObjectProphecy $dataSource;

    /** @var ObjectProphecy<ExpressionBuilderInterface> */
    private ObjectProphecy $expressionBuilder;

    protected function setUp(): void
    {
        $this->expressionBuilder = $this->prophesize(ExpressionBuilderInterface::class);
        $this->dataSource = $this->prophesize(DataSourceInterface::class);
        $this->dataSource->getExpressionBuilder()->willReturn($this->expressionBuilder->reveal());
    }

    /** @test */
    public function it_lists_the_cards_with_nothing_left(): void
    {
        $this->expressionBuilder->lessThanOrEqual('amount', 0)->willReturn('spent');
        $this->dataSource->restrict('spent')->shouldBeCalledOnce();

        (new GiftCardSpentFilter())->apply($this->dataSource->reveal(), 'spent', 'true', []);
    }

    /**
     * A balance above 0 is what a card needs to be usable, and anything else is spent
     *
     * @test
     */
    public function it_lists_the_cards_with_a_balance_left(): void
    {
        $this->expressionBuilder->greaterThan('amount', 0)->willReturn('not spent');
        $this->dataSource->restrict('not spent')->shouldBeCalledOnce();

        (new GiftCardSpentFilter())->apply($this->dataSource->reveal(), 'spent', 'false', []);
    }

    /**
     * @test
     *
     * @dataProvider valuesThatFilterByNothing
     */
    public function it_filters_by_nothing_unless_asked_yes_or_no(mixed $data): void
    {
        $this->dataSource->restrict(Argument::cetera())->shouldNotBeCalled();

        (new GiftCardSpentFilter())->apply($this->dataSource->reveal(), 'spent', $data, []);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatFilterByNothing(): iterable
    {
        yield 'all, the empty choice' => [''];
        yield 'not given' => [null];
        yield 'a value the form does not offer' => ['maybe'];
    }
}
