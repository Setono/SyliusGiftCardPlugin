<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use function preg_replace;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    private GiftCardRepositoryInterface $giftCardRepository;

    /** @var positive-int */
    private int $codeLength;

    /**
     * @param positive-int $codeLength
     */
    public function __construct(GiftCardRepositoryInterface $giftCardRepository, int $codeLength)
    {
        Assert::greaterThan($codeLength, 0);

        $this->giftCardRepository = $giftCardRepository;
        $this->codeLength = $codeLength;
    }

    public function generate(): string
    {
        do {
            // if we didn't remove the 'hard to read' characters we would only have to
            // generate codeLength / 2 bytes because hex uses two characters to represent one byte
            $code = bin2hex(random_bytes($this->codeLength));
            $code = preg_replace('/[01]/', '', $code); // remove hard to read characters
            $code = mb_strtoupper(mb_substr($code, 0, $this->codeLength));
        } while (mb_strlen($code) !== $this->codeLength || $this->exists($code));

        return $code;
    }

    private function exists(string $code): bool
    {
        return null !== $this->giftCardRepository->findOneByCode($code);
    }
}
