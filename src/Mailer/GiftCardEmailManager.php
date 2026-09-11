<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Mailer;

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

        $this->send(Emails::GIFT_CARDS_FROM_ORDER, $email, $giftCards, [
            'order' => $order,
            'channel' => $order->getChannel(),
            'localeCode' => $order->getLocaleCode(),
        ]);
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

        $this->send(Emails::GIFT_CARD, $email, [$giftCard], [
            'channel' => $channel,
            'localeCode' => $channel?->getDefaultLocale()?->getCode(),
        ]);
    }

    /**
     * @param list<GiftCardInterface> $giftCards
     * @param array<string, mixed> $data
     */
    private function send(string $code, string $email, array $giftCards, array $data): void
    {
        // Attachments are written to a unique per-send directory so each can carry a clean, customer-facing filename
        // (gift-card-<code>.pdf) without risk of collision, and the whole directory is removed afterwards
        $directory = $this->createTemporaryDirectory();
        $attachments = [];

        try {
            foreach ($giftCards as $giftCard) {
                $path = sprintf('%s/gift-card-%s.pdf', $directory, (string) $giftCard->getCode());
                file_put_contents($path, $this->pdfGenerator->generate($giftCard));
                $attachments[] = $path;
            }

            $this->sender->send(
                $code,
                [$email],
                array_merge($data, ['giftCards' => $giftCards]),
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
