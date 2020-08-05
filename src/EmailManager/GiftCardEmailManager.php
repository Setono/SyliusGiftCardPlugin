<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Resolver\CustomerChannelResolverInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class GiftCardEmailManager implements GiftCardEmailManagerInterface
{
    /** @var SenderInterface */
    private $sender;

    /** @var LocaleAwareInterface */
    private $translator;
    /**
     * @var CustomerChannelResolverInterface
     */
    private $customerChannelResolver;

    public function __construct(
        SenderInterface $sender,
        LocaleAwareInterface $translator,
        CustomerChannelResolverInterface $customerChannelResolver
    ) {
        $this->sender = $sender;
        $this->translator = $translator;
        $this->customerChannelResolver = $customerChannelResolver;
    }

    public function sendEmailToCustomerWithGiftCard(CustomerInterface $customer, GiftCardInterface $giftCard): void
    {
        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $channel = $this->customerChannelResolver->resolve($customer);

        $defaultLocale = $channel->getDefaultLocale();
        if (null === $defaultLocale) {
            return;
        }

        $this->wrapTemporaryLocale((string) $defaultLocale->getCode(), function () use ($email, $customer, $giftCard, $channel): void {
            $this->sender->send(
                Emails::GIFT_CARD_CUSTOMER,
                [$email],
                ['customer' => $customer, 'giftCard' => $giftCard, 'channel' => $channel]
            );
        });
    }

    public function sendEmailWithGiftCardsFromOrder(OrderInterface $order, array $giftCards): void
    {
        $customer = $order->getCustomer();
        if (null === $customer) {
            return;
        }

        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $channel = $order->getChannel();
        if (null === $channel) {
            return;
        }

        $defaultLocale = $channel->getDefaultLocale();
        if (null === $defaultLocale) {
            return;
        }

        $this->wrapTemporaryLocale((string) $defaultLocale->getCode(), function () use ($email, $giftCards, $order, $channel): void {
            $this->sender->send(
                Emails::GIFT_CARD_ORDER,
                [$email],
                ['giftCards' => $giftCards, 'order' => $order, 'channel' => $channel]
            );
        });
    }

    /**
     * This method will wrap the callback in a flow where the locale is changed
     * in the translator before the callback and changed back after the callback
     */
    private function wrapTemporaryLocale(string $locale, callable $callback): void
    {
        $oldLocale = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        $callback();

        $this->translator->setLocale($oldLocale);
    }
}
