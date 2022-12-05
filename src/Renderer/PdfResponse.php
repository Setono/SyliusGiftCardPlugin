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
    private string $originalContent;

    public function __construct(string $content, string $filename = 'gift_card.pdf')
    {
        parent::__construct($content);

        $this->originalContent = $content;

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);

        /** @psalm-suppress UninitializedProperty,PossiblyNullReference */
        $this->headers->set('Content-Type', 'application/pdf');

        /** @psalm-suppress PossiblyNullReference */
        $this->headers->set('Content-Disposition', $disposition);
    }

    public static function fromGiftCard(string $content, GiftCardInterface $giftCard): self
    {
        return new self($content, sprintf('gift_card_%s.pdf', (string) $giftCard->getCode()));
    }

    public function encode(): self
    {
        $this->content = base64_encode($this->originalContent);

        return $this;
    }

    public function doNotEncode(): self
    {
        $this->content = $this->originalContent;

        return $this;
    }
}
