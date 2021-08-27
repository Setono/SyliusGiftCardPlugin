<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\Controller\Action;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\Controller\Action\GiftCardBalanceAction;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardBalanceCollection;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardBalanceActionTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_returns_the_balance(): void
    {
        $giftCard1 = new GiftCard();
        $giftCard1->setCurrencyCode('EUR');
        $giftCard1->setAmount(25);
        $giftCard2 = new GiftCard();
        $giftCard2->setCurrencyCode('EUR');
        $giftCard2->setAmount(56);

        $giftCard3 = new GiftCard();
        $giftCard3->setCurrencyCode('USD');
        $giftCard3->setAmount(31);
        $giftCard4 = new GiftCard();
        $giftCard4->setCurrencyCode('USD');
        $giftCard4->setAmount(84);
        $giftCard5 = new GiftCard();
        $giftCard5->setCurrencyCode('USD');
        $giftCard5->setAmount(86);

        $expectedBalanceCollection = new GiftCardBalanceCollection();
        $expectedBalanceCollection->addGiftCard($giftCard1);
        $expectedBalanceCollection->addGiftCard($giftCard2);
        $expectedBalanceCollection->addGiftCard($giftCard3);
        $expectedBalanceCollection->addGiftCard($giftCard4);
        $expectedBalanceCollection->addGiftCard($giftCard5);

        $giftCardRepository = $this->prophesize(GiftCardRepositoryInterface::class);

        $giftCardRepository->findEnabled()->willReturn([$giftCard1, $giftCard2, $giftCard3, $giftCard4, $giftCard5]);

        $giftCardBalanceAction = new GiftCardBalanceAction($giftCardRepository->reveal());
        $returnedBalanceCollection = $giftCardBalanceAction();

        self::assertEquals($expectedBalanceCollection, $returnedBalanceCollection);
    }
}
