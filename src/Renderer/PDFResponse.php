<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Renderer;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class PDFResponse
{
    private string $content;

    private bool $encode;

    /**
     * @param string $content the PDF string
     * @param bool $encode whether to encode the content as base64 when printed
     */
    public function __construct(string $content, bool $encode = false)
    {
        $this->content = $content;
        $this->encode = $encode;
    }

    public function __toString(): string
    {
        return $this->getContent();
    }

    public function getContent(): string
    {
        if ($this->encode) {
            return base64_encode($this->content);
        }

        return $this->content;
    }

    public function encode(): self
    {
        $this->encode = true;

        return $this;
    }

    public function doNotEncode(): self
    {
        $this->encode = false;

        return $this;
    }

    public function getHttpResponse(string $filename = 'gift_card.pdf'): Response
    {
        $response = new Response($this->content); // should not be base64 encoded

        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
