<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Exception;
use Safe\Exceptions\PcreException;
use Safe\Exceptions\StringsException;
use function Safe\preg_replace;
use function Safe\substr;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /** @var RepositoryInterface */
    private $giftCardCodeRepository;

    /** @var int */
    private $codeLength;

    public function __construct(RepositoryInterface $giftCardCodeRepository, int $codeLength = 12)
    {
        $this->giftCardCodeRepository = $giftCardCodeRepository;
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
        return null !== $this->giftCardCodeRepository->findOneBy(['code' => $code]);
    }
}
