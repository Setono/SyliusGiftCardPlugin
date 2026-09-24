<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventSubscriber\SendGiftCardEmailSubscriber;
use Setono\SyliusGiftCardPlugin\Mailer\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\CustomerInterface;

/**
 * A gift card the customer cannot use — created disabled, already expired or with no balance left — must not
 * be emailed: it would arrive as a gift that does not work. The admin sends it themselves, from the show page,
 * once the card is usable
 */
final class SendGiftCardEmailSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_emails_a_usable_gift_card(): void
    {
        $giftCard = $this->giftCard();

        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard($giftCard)->shouldBeCalledOnce();

        $this->dispatch($emailManager->reveal(), $giftCard);
    }

    /** @test */
    public function it_does_not_email_a_disabled_gift_card(): void
    {
        $giftCard = $this->giftCard();
        $giftCard->setEnabled(false);

        $this->assertNothingIsSent($giftCard);
    }

    /** @test */
    public function it_does_not_email_an_expired_gift_card(): void
    {
        $giftCard = $this->giftCard();
        $giftCard->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->assertNothingIsSent($giftCard);
    }

    /** @test */
    public function it_does_not_email_a_gift_card_without_a_balance(): void
    {
        $giftCard = $this->giftCard();
        $giftCard->setAmount(0);

        $this->assertNothingIsSent($giftCard);
    }

    /** @test */
    public function it_does_not_email_when_the_admin_did_not_ask_for_a_notification(): void
    {
        $giftCard = $this->giftCard();
        $giftCard->setSendNotificationEmail(false);

        $this->assertNothingIsSent($giftCard);
    }

    /** @test */
    public function it_does_not_email_when_there_is_no_customer(): void
    {
        $giftCard = $this->giftCard();
        $giftCard->setCustomer(null);

        $this->assertNothingIsSent($giftCard);
    }

    /**
     * Sylius' resource controller dispatches the event once an admin created a gift card, and a card bought in the
     * shop is never created through it
     *
     * @test
     */
    public function it_listens_to_the_admin_creating_a_gift_card(): void
    {
        self::assertSame(
            ['setono_sylius_gift_card.gift_card.post_create' => 'onGiftCardPostCreate'],
            SendGiftCardEmailSubscriber::getSubscribedEvents(),
        );
    }

    /** @test */
    public function it_ignores_an_event_about_anything_but_a_gift_card(): void
    {
        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $subscriber = new SendGiftCardEmailSubscriber($emailManager->reveal());
        $subscriber->onGiftCardPostCreate(new ResourceControllerEvent(new \stdClass()));
    }

    private function assertNothingIsSent(GiftCardInterface $giftCard): void
    {
        $emailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $emailManager->sendGiftCard(Argument::cetera())->shouldNotBeCalled();

        $this->dispatch($emailManager->reveal(), $giftCard);
    }

    private function dispatch(GiftCardEmailManagerInterface $emailManager, GiftCardInterface $giftCard): void
    {
        $subscriber = new SendGiftCardEmailSubscriber($emailManager);
        $subscriber->onGiftCardPostCreate(new ResourceControllerEvent($giftCard));
    }

    /**
     * A gift card as the admin create form leaves it: usable, with a customer, asking for a notification
     */
    private function giftCard(): GiftCardInterface
    {
        $giftCard = new GiftCard();
        $giftCard->setEnabled(true);
        $giftCard->setAmount(10_000);
        $giftCard->setCustomer($this->prophesize(CustomerInterface::class)->reveal());

        return $giftCard;
    }
}
