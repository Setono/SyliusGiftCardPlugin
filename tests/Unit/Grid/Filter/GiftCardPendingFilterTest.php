<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Grid\Filter;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusGiftCardPlugin\Grid\Filter\GiftCardPendingFilter;
use Sylius\Component\Grid\Data\DataSourceInterface;

/**
 * The DQL itself runs against the database in AdminGridTest
 */
final class GiftCardPendingFilterTest extends TestCase
{
    use ProphecyTrait;

    /** GiftCard::isPending(): disabled, on a unit, and no ledger row yet */
    private const PENDING = 'o.enabled = false AND o.orderItemUnit IS NOT NULL AND o.transactions IS EMPTY';

    /** @var ObjectProphecy<DataSourceInterface> */
    private ObjectProphecy $dataSource;

    protected function setUp(): void
    {
        $this->dataSource = $this->prophesize(DataSourceInterface::class);
    }

    /**
     * @test
     *
     * @dataProvider valuesThatHideThem
     */
    public function it_hides_the_pending_cards_by_default(mixed $data): void
    {
        $this->dataSource->restrict(sprintf('NOT (%s)', self::PENDING))->shouldBeCalledOnce();

        (new GiftCardPendingFilter())->apply($this->dataSource->reveal(), 'pending', $data, []);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatHideThem(): iterable
    {
        yield 'hide, the empty choice and the default' => [''];
        yield 'not given' => [null];
        // so a value made up in the address cannot show them either
        yield 'a value the form does not offer' => ['all'];
    }

    /** @test */
    public function it_lists_the_pending_cards_along_with_the_others_when_asked(): void
    {
        $this->dataSource->restrict(Argument::cetera())->shouldNotBeCalled();

        (new GiftCardPendingFilter())->apply($this->dataSource->reveal(), 'pending', GiftCardPendingFilter::SHOW, []);
    }

    /** @test */
    public function it_lists_nothing_but_the_pending_cards_when_asked(): void
    {
        $this->dataSource->restrict(self::PENDING)->shouldBeCalledOnce();

        (new GiftCardPendingFilter())->apply($this->dataSource->reveal(), 'pending', GiftCardPendingFilter::ONLY, []);
    }
}
