<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Mailer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardDeliveryType;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Pdf\GiftCardPdfGeneratorInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final class GiftCardEmailManager implements GiftCardEmailManagerInterface
{
    public function __construct(
        private readonly SenderInterface $sender,
        private readonly GiftCardPdfGeneratorInterface $pdfGenerator,
        private readonly bool $emailPhysicalCards = false,
    ) {
    }

    public function sendGiftCardsFromOrder(OrderInterface $order, array $giftCards): void
    {
        if ([] === $giftCards) {
            return;
        }

        $email = $order->getCustomer()?->getEmail();
        if (null === $email) {
            return;
        }

        // Sent automatically when the order is paid, which for a physical card is before it has been shipped. Its
        // code is printed on the card, so emailing it too would make the card spendable before it arrives and
        // duplicate what is in the envelope - unless the merchant asked for the digital backup
        $this->send(Emails::GIFT_CARDS_FROM_ORDER, $email, $giftCards, [
            'order' => $order,
            'channel' => $order->getChannel(),
            'localeCode' => $order->getLocaleCode(),
        ], $this->emailPhysicalCards);
    }

    public function sendGiftCard(GiftCardInterface $giftCard): void
    {
        $customer = $giftCard->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $email = $customer->getEmail();
        if (null === $email) {
            return;
        }

        $channel = $giftCard->getChannel();

        // Only ever sent because an admin asked for it: when creating the card, or from the gift card's show page,
        // which is how a physical card the customer lost or never received is replaced. The admin has decided that
        // the customer gets the code, so it is disclosed, PDF included, whatever the delivery type
        $this->send(Emails::GIFT_CARD, $email, [$giftCard], [
            'channel' => $channel,
            // A Sylius customer carries no locale of its own, so the locale the card was bought in is the best
            // approximation of the recipient's language. Resolved exactly like the PDF generator does it, so the
            // email body and the PDF attached to it are never written in two different languages
            'localeCode' => $giftCard->getOrder()?->getLocaleCode() ?? $channel?->getDefaultLocale()?->getCode(),
        ], true);
    }

    /**
     * @param list<GiftCardInterface> $giftCards
     * @param array<string, mixed> $data
     * @param bool $disclosePhysicalCards whether the code and the PDF of a physical gift card go into the email. A
     *                                    virtual gift card is delivered by the email, so its code and PDF always do
     */
    private function send(string $code, string $email, array $giftCards, array $data, bool $disclosePhysicalCards): void
    {
        // Attachments are written to a unique per-send directory so each can carry a clean, customer-facing filename
        // (gift-card-<code>.pdf) without risk of collision, and the whole directory is removed afterwards
        $directory = $this->createTemporaryDirectory();
        $attachments = [];

        try {
            foreach ($giftCards as $giftCard) {
                if (!$disclosePhysicalCards && GiftCardDeliveryType::Physical === $giftCard->getDeliveryType()) {
                    continue;
                }

                $path = sprintf('%s/gift-card-%s.pdf', $directory, (string) $giftCard->getCode());
                file_put_contents($path, $this->pdfGenerator->generate($giftCard));
                $attachments[] = $path;
            }

            $this->sender->send(
                $code,
                [$email],
                array_merge($data, [
                    'giftCards' => $giftCards,
                    // The templates apply the same rule to the body as this method applies to the attachments
                    'disclosePhysicalCards' => $disclosePhysicalCards,
                ]),
                $attachments,
            );
        } finally {
            foreach ($attachments as $attachment) {
                if (is_file($attachment)) {
                    unlink($attachment);
                }
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function createTemporaryDirectory(): string
    {
        $directory = (string) tempnam(sys_get_temp_dir(), 'ssgc_');
        unlink($directory);
        mkdir($directory, 0o700, true);

        return $directory;
    }
}
