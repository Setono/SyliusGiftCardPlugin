<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\EventSubscriber\GiftCardDeletionSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;

/**
 * A card whose balance has moved, or that an order paid for, is part of the shop's books: deleting it would take its
 * ledger with it. The subscriber stops such a deletion before Sylius' resource controller removes the card, and the
 * admin is shown why
 */
final class GiftCardDeletionSubscriberTest extends TestCase
{
    /** @test */
    public function it_runs_before_a_gift_card_is_deleted(): void
    {
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.pre_delete' => 'onGiftCardPreDelete'],
            GiftCardDeletionSubscriber::getSubscribedEvents(),
        );
    }

    /** @test */
    public function it_lets_a_gift_card_that_was_never_used_be_deleted(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);

        $event = new ResourceControllerEvent($giftCard);
        (new GiftCardDeletionSubscriber())->onGiftCardPreDelete($event);

        self::assertFalse($event->isStopped());
    }

    /** @test */
    public function it_stops_the_deletion_of_a_gift_card_whose_balance_has_moved(): void
    {
        $giftCard = new GiftCard();
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(3000);

        $event = new ResourceControllerEvent($giftCard);
        (new GiftCardDeletionSubscriber())->onGiftCardPreDelete($event);

        self::assertTrue($event->isStopped());
        self::assertSame('error', $event->getMessageType());
        self::assertSame('setono_sylius_gift_card.gift_card.delete_error', $event->getMessage());
    }

    /** @test */
    public function it_refuses_to_judge_anything_but_a_gift_card(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        (new GiftCardDeletionSubscriber())->onGiftCardPreDelete(new ResourceControllerEvent(new Customer()));
    }
}
