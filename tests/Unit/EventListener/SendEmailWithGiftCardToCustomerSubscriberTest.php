<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\EventListener\SendEmailWithGiftCardToCustomerSubscriber;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;

final class SendEmailWithGiftCardToCustomerSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_an_error_if_the_subject_is_not_gift_card(): void
    {
        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $event = $this->prophesize(ResourceControllerEvent::class);
        $event->getSubject()->willReturn(new \stdClass());

        $this->expectException(UnexpectedTypeException::class);
        $subscriber = new SendEmailWithGiftCardToCustomerSubscriber($giftCardEmailManager->reveal());
        $subscriber->postCreate($event->reveal());
    }

    /**
     * @test
     */
    public function it_does_not_send_email_if_no_customer(): void
    {
        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $event = $this->prophesize(ResourceControllerEvent::class);
        $event->getSubject()->willReturn($giftCard);

        $subscriber = new SendEmailWithGiftCardToCustomerSubscriber($giftCardEmailManager->reveal());
        $subscriber->postCreate($event->reveal());

        $giftCardEmailManager->sendEmailToCustomerWithGiftCard(Argument::any(), Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_does_not_send_email_if_it_was_not_asked(): void
    {
        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $customer = $this->prophesize(CustomerInterface::class);
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getCustomer()->willReturn($customer);
        $giftCard->getSendNotificationEmail()->willReturn(false);
        $event = $this->prophesize(ResourceControllerEvent::class);
        $event->getSubject()->willReturn($giftCard);

        $subscriber = new SendEmailWithGiftCardToCustomerSubscriber($giftCardEmailManager->reveal());
        $subscriber->postCreate($event->reveal());

        $giftCardEmailManager->sendEmailToCustomerWithGiftCard(Argument::any(), Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_sends_email_if_it_was_asked(): void
    {
        $giftCardEmailManager = $this->prophesize(GiftCardEmailManagerInterface::class);
        $customer = $this->prophesize(CustomerInterface::class);
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->getCustomer()->willReturn($customer);
        $giftCard->getSendNotificationEmail()->willReturn(true);
        $event = $this->prophesize(ResourceControllerEvent::class);
        $event->getSubject()->willReturn($giftCard);

        $subscriber = new SendEmailWithGiftCardToCustomerSubscriber($giftCardEmailManager->reveal());
        $subscriber->postCreate($event->reveal());

        $giftCardEmailManager->sendEmailToCustomerWithGiftCard(Argument::any(), Argument::any())->shouldHaveBeenCalled();
    }
}
