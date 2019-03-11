<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /**
     * @var RepositoryInterface
     */
    private $giftCardCodeRepository;

    public function __construct(RepositoryInterface $giftCardCodeRepository)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
    }

    /**
     * @param int $codeLength
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function generate(int $codeLength = self::DEFAULT_CODE_LENGTH): string
    {
        Assert::range($codeLength, 1, 40, 'Invalid %d code length. Should be between %d and %d');

        do {
            $hash = bin2hex(random_bytes(20));
            $code = strtoupper(substr($hash, 0, $codeLength));
        } while ($this->isCodeUsed($code));

        return $code;
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    private function isCodeUsed(string $code): bool
    {
        return null !== $this->giftCardCodeRepository->findOneBy(['code' => $code]);
    }
}
