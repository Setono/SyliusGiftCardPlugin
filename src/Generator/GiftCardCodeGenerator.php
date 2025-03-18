<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use function preg_replace;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
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
            // if we didn't remove the 'hard to read' characters we would only have to
            // generate codeLength / 2 bytes because hex uses two characters to represent one byte
            /** @psalm-suppress ArgumentTypeCoercion */
            $code = bin2hex(random_bytes($this->codeLength));
            $code = preg_replace('/[01]/', '', $code); // remove hard to read characters
            Assert::string($code);

            $code = mb_strtoupper(mb_substr($code, 0, $this->codeLength));
        } while (mb_strlen($code) !== $this->codeLength || $this->exists($code));

        return $code;
    }

    private function exists(string $code): bool
    {
        return $this->giftCardRepository->findOneByCode($code) instanceof GiftCardInterface;
    }
}
