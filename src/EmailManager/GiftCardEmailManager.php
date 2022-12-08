<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EmailManager;

use Setono\SyliusGiftCardPlugin\Mailer\Emails;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Resolver\CustomerChannelResolverInterface;
use Setono\SyliusGiftCardPlugin\Resolver\LocaleResolverInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class GiftCardEmailManager implements GiftCardEmailManagerInterface
{
    private SenderInterface $sender;

    private LocaleAwareInterface $translator;

    private CustomerChannelResolverInterface $customerChannelResolver;

    private LocaleResolverInterface $localeResolver;

    public function __construct(
        SenderInterface $sender,
        LocaleAwareInterface $translator,
        CustomerChannelResolverInterface $customerChannelResolver,
        LocaleResolverInterface $customerLocaleResolver
    ) {
        $this->sender = $sender;
        $this->translator = $translator;
        $this->customerChannelResolver = $customerChannelResolver;
        $this->localeResolver = $customerLocaleResolver;
    }

    public function sendEmailToCustomerWithGiftCard(CustomerInterface $customer, GiftCardInterface $giftCard): void
    {
        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $localeCode = $this->localeResolver->resolveFromCustomer($customer);
        $channel = $this->customerChannelResolver->resolve($customer);

        $this->wrapTemporaryLocale($localeCode, function () use ($email, $customer, $giftCard, $channel, $localeCode): void {
            /** @psalm-suppress DeprecatedMethod */
            $this->sender->send(
                Emails::GIFT_CARD_CUSTOMER,
                [$email],
                [
                    'customer' => $customer,
                    'giftCard' => $giftCard,
                    'channel' => $channel,
                    // We still need to inject locale to templates because layout is using it
                    'localeCode' => $localeCode,
                ]
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

        $localeCode = $this->localeResolver->resolveFromOrder($order);

        $this->wrapTemporaryLocale($localeCode, function () use ($email, $giftCards, $order, $channel, $localeCode): void {
            /** @psalm-suppress DeprecatedMethod */
            $this->sender->send(
                Emails::GIFT_CARD_ORDER,
                [$email],
                [
                    'giftCards' => $giftCards,
                    'order' => $order,
                    'channel' => $channel,
                    // We still need to inject locale to templates because layout is using it
                    'localeCode' => $localeCode,
                ]
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
