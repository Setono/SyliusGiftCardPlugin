<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Exception;
use Safe\Exceptions\PcreException;
use Safe\Exceptions\StringsException;
use function Safe\preg_replace;
use function Safe\substr;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var int */
    private $codeLength;

    public function __construct(GiftCardRepositoryInterface $giftCardRepository, int $codeLength = 12)
    {
        $this->giftCardRepository = $giftCardRepository;
        $this->codeLength = $codeLength;
    }

    /**
     * @throws Exception
     * @throws PcreException
     * @throws StringsException
     */
    public function generate(): string
    {
        do {
            // if we didn't remove the 'hard to read' characters we would only have to
            // generate codeLength / 2 bytes because hex uses two characters to represent one byte
            $code = bin2hex(random_bytes($this->codeLength));
            $code = preg_replace('/[01]/', '', $code); // remove hard to read characters
            $code = strtoupper(substr($code, 0, $this->codeLength));
        } while (strlen($code) !== $this->codeLength || $this->exists($code));

        return $code;
    }

    private function exists(string $code): bool
    {
        return null !== $this->giftCardRepository->findOneByCode($code);
    }
}
