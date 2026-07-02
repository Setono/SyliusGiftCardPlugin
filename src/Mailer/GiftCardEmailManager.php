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

        $this->send(Emails::GIFT_CARD, $email, [$giftCard], [
            'channel' => $giftCard->getChannel(),
        ]);
    }

    /**
     * @param list<GiftCardInterface> $giftCards
     * @param array<string, mixed> $data
     */
    private function send(string $code, string $email, array $giftCards, array $data): void
    {
        $attachments = [];

        try {
            foreach ($giftCards as $giftCard) {
                $attachments[] = $this->createAttachment($giftCard);
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
        }
    }

    private function createAttachment(GiftCardInterface $giftCard): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ssgc_pdf_');
        file_put_contents($path, $this->pdfGenerator->generate($giftCard));

        // Give the temporary file a .pdf extension so the mail client shows a sensible name
        $pdfPath = $path . '.pdf';
        rename($path, $pdfPath);

        return $pdfPath;
    }
}
