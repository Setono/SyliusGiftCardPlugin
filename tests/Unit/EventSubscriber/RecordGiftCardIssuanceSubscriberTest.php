<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\RecordGiftCardIssuanceSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Operator\GiftCardBalanceOperatorInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Customer;

/**
 * A card created in the admin holds its balance from the moment it is saved, so its opening balance goes into the
 * ledger right away. It is recorded before the resource controller saves the card, so the controller's flush writes
 * the card and its ledger row together, and the subscriber flushes nothing itself
 */
final class RecordGiftCardIssuanceSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_runs_last_before_a_gift_card_is_created(): void
    {
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.pre_create' => ['recordIssuance', -1000]],
            RecordGiftCardIssuanceSubscriber::getSubscribedEvents(),
        );
    }

    /** @test */
    public function it_records_the_issuance_of_the_created_gift_card(): void
    {
        $giftCard = new GiftCard();

        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard)->shouldBeCalledOnce();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal());
        $subscriber->recordIssuance(new ResourceControllerEvent($giftCard));
    }

    /** @test */
    public function it_ignores_anything_but_a_gift_card(): void
    {
        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue(Argument::any())->shouldNotBeCalled();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal());
        $subscriber->recordIssuance(new ResourceControllerEvent(new Customer()));
    }
}
