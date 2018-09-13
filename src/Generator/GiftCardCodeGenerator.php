<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /** @var RepositoryInterface */
    private $giftCardCodeRepository;

    /** @var int */
    private $codeLength;

    public function __construct(RepositoryInterface $giftCardCodeRepository, int $codeLength = 9)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
        $this->codeLength = $codeLength;
    }

    public function generate(): string
    {
        Assert::nullOrRange($this->codeLength, 1, 40, 'Invalid %d code length should be between %d and %d');

        do {
            $hash = bin2hex(random_bytes(20));
            $code = strtoupper(substr($hash, 0, $this->codeLength));
        } while ($this->isUsedCode($code));

        return $code;
    }

    private function isUsedCode(string $code): bool
    {
        return null !== $this->giftCardCodeRepository->findOneBy(['code' => $code]);
    }
}
