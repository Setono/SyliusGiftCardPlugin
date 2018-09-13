<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\EmailManager;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManager;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class GiftCardOrderEmailManagerSpec extends ObjectBehavior
{
    function let(SenderInterface $sender): void
    {
        $this->beConstructedWith($sender);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardOrderEmailManager::class);
    }

    function it_implements_gift_card_order_email_manager_interface(): void
    {
        $this->shouldHaveType(GiftCardOrderEmailManagerInterface::class);
    }

    function it_sends_email_with_gift_card_codes(
        OrderInterface $order,
        CustomerInterface $customer,
        GiftCardCodeInterface $giftCardCode,
        SenderInterface $sender
    ): void {
        $customer->getEmail()->willReturn('exmaple@shop.com');
        $order->getCustomer()->willReturn($customer);

        $sender->send(
            GiftCardOrderEmailManagerInterface::EMAIL_CONFIG_NAME,
            ['exmaple@shop.com'],
            ['giftCardCodes' => [$giftCardCode], 'order' => $order]
        )->shouldBeCalled();

        $this->sendEmailWithGiftCardCodes($order, [$giftCardCode]);
    }
}
