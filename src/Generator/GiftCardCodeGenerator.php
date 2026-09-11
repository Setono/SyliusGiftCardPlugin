<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /**
     * Alphabet without visually ambiguous characters (no 0/O, 1/I/L) so codes are easy to read and dictate
     */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** @var positive-int */
    private readonly int $codeLength;

    /**
     * @param positive-int $codeLength
     */
    public function __construct(private readonly GiftCardRepositoryInterface $giftCardRepository, int $codeLength)
    {
        Assert::greaterThan($codeLength, 0);
        $this->codeLength = $codeLength;
    }

    public function generate(): string
    {
        do {
            $code = $this->randomCode();
        } while ($this->exists($code));

        return $code;
    }

    private function randomCode(): string
    {
        $alphabetLength = strlen(self::ALPHABET);

        $code = '';
        for ($i = 0; $i < $this->codeLength; ++$i) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }

    private function exists(string $code): bool
    {
        return null !== $this->giftCardRepository->findOneByCode($code);
    }
}
