<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\EmailManager;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManager;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Resolver\CustomerChannelResolverInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class GiftCardEmailManagerSpec extends ObjectBehavior
{
    public function let(
        SenderInterface $sender,
        LocaleAwareInterface $translator,
        CustomerChannelResolverInterface $customerChannelResolver
    ): void {
        $this->beConstructedWith($sender, $translator, $customerChannelResolver);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardEmailManager::class);
    }

    public function it_implements_gift_card_order_email_manager_interface(): void
    {
        $this->shouldHaveType(GiftCardEmailManagerInterface::class);
    }

    public function it_sends_email_with_gift_card_codes(
        OrderInterface $order,
        CustomerInterface $customer,
        GiftCardInterface $giftCard,
        SenderInterface $sender,
        ChannelInterface $channel,
    LocaleInterface $locale
    ): void {
        $customer->getEmail()->willReturn('example@shop.com');
        $order->getCustomer()->willReturn($customer);
        $order->getChannel()->willReturn($channel);
        $channel->getDefaultLocale()->willReturn($locale);
        $locale->getCode()->willReturn('en_US');

        $sender->send(
            Emails::GIFT_CARD_ORDER,
            ['example@shop.com'],
            ['giftCards' => [$giftCard], 'order' => $order, 'channel' => $channel]
        )->shouldBeCalled();

        $this->sendEmailWithGiftCardsFromOrder($order, [$giftCard]);
    }
}
