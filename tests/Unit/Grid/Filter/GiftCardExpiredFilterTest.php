<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Grid\Filter;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardExpiredFilter;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Data\ExpressionBuilderInterface;

final class GiftCardExpiredFilterTest extends TestCase
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

    /**
     * A card is expired once now is past its expiry date, the way GiftCardInterface::isExpired() judges it
     *
     * @test
     */
    public function it_lists_the_cards_whose_expiry_date_has_passed(): void
    {
        $this->expressionBuilder->lessThan('expiresAt', self::now())->willReturn('expired');
        $this->dataSource->restrict('expired')->shouldBeCalledOnce();

        (new GiftCardExpiredFilter())->apply($this->dataSource->reveal(), 'expired', 'true', []);
    }

    /**
     * A card without an expiry date never expires
     *
     * @test
     */
    public function it_lists_the_cards_that_have_not_expired_or_never_do(): void
    {
        $this->expressionBuilder->isNull('expiresAt')->willReturn('never expires');
        $this->expressionBuilder->greaterThanOrEqual('expiresAt', self::now())->willReturn('expires later');
        $this->expressionBuilder->orX('never expires', 'expires later')->willReturn('not expired');
        $this->dataSource->restrict('not expired')->shouldBeCalledOnce();

        (new GiftCardExpiredFilter())->apply($this->dataSource->reveal(), 'expired', 'false', []);
    }

    /**
     * @test
     *
     * @dataProvider valuesThatFilterByNothing
     */
    public function it_filters_by_nothing_unless_asked_yes_or_no(mixed $data): void
    {
        $this->dataSource->restrict(Argument::cetera())->shouldNotBeCalled();

        (new GiftCardExpiredFilter())->apply($this->dataSource->reveal(), 'expired', $data, []);
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

    /**
     * A moment taken while the filter runs
     */
    private static function now(): Argument\Token\TokenInterface
    {
        $before = new \DateTimeImmutable();

        return Argument::that(static fn (mixed $date): bool => $date instanceof \DateTimeImmutable && $date >= $before && $date <= new \DateTimeImmutable());
    }
}
