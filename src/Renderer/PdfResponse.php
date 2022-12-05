<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-suppress PropertyNotSetInConstructor see this issue: https://github.com/psalm/psalm-plugin-symfony/issues/290
 */
final class PdfResponse extends Response
{
    public function __construct(string $content, string $filename = 'gift_card.pdf')
    {
        parent::__construct($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    public static function fromGiftCard(string $content, GiftCardInterface $giftCard): self
    {
        return new self($content, sprintf('gift_card_%s.pdf', (string) $giftCard->getCode()));
    }

    /**
     * Returns the PDF content as a base64 encoded string
     */
    public function getEncodedContent(): string
    {
        return base64_encode($this->content);
    }
}
