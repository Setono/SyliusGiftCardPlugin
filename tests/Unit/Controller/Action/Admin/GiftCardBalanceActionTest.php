<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Controller\Action\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\GiftCardBalanceAction;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class GiftCardBalanceActionTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_renders_the_outstanding_balance_per_currency(): void
    {
        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);
        $giftCardRepository->findBalance()->willReturn([
            ['currencyCode' => 'USD', 'count' => 2, 'amount' => 7501],
            ['currencyCode' => 'EUR', 'count' => 1, 'amount' => 1234],
        ]);

        $twig = new Environment(new ArrayLoader([
            '@SetonoSyliusGiftCardPlugin/admin/gift_card/balance.html.twig' => '{% for balance in balances %}{{ balance.currencyCode }}:{{ balance.count }}:{{ balance.amount }};{% endfor %}',
        ]));

        $response = (new GiftCardBalanceAction($giftCardRepository->reveal(), $twig))();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('USD:2:7501;EUR:1:1234;', $response->getContent());
    }
}
