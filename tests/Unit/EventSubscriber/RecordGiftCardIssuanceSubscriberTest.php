<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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
 * ledger right away. The resource controller has already flushed the card by then, so the subscriber flushes the
 * ledger row itself
 */
final class RecordGiftCardIssuanceSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_runs_once_a_gift_card_has_been_created(): void
    {
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.post_create' => 'recordIssuance'],
            RecordGiftCardIssuanceSubscriber::getSubscribedEvents(),
        );
    }

    /** @test */
    public function it_records_the_issuance_of_the_created_gift_card_and_flushes_it(): void
    {
        $giftCard = new GiftCard();

        $calls = [];

        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue($giftCard)->will(static function () use (&$calls): void {
            $calls[] = 'issue';
        });

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->flush()->will(static function () use (&$calls): void {
            $calls[] = 'flush';
        });

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(GiftCard::class)->willReturn($manager->reveal());

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal(), $managerRegistry->reveal());
        $subscriber->recordIssuance(new ResourceControllerEvent($giftCard));

        // The ledger row only exists once issue() has run, so the flush has to come after it
        self::assertSame(['issue', 'flush'], $calls);
    }

    /** @test */
    public function it_ignores_anything_but_a_gift_card(): void
    {
        $balanceOperator = $this->prophesize(GiftCardBalanceOperatorInterface::class);
        $balanceOperator->issue(Argument::any())->shouldNotBeCalled();

        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->shouldNotBeCalled();

        $subscriber = new RecordGiftCardIssuanceSubscriber($balanceOperator->reveal(), $managerRegistry->reveal());
        $subscriber->recordIssuance(new ResourceControllerEvent(new Customer()));
    }
}
