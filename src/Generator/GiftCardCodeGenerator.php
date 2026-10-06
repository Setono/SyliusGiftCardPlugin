<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Generator;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Webmozart\Assert\Assert;

final class GiftCardCodeGenerator implements GiftCardCodeGeneratorInterface
{
    /**
     * Alphabet without visually ambiguous characters (no 0/O, 1/I/L) so codes are easy to read and dictate
     */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepository,
        private readonly int $codeLength,
        private readonly int $minimumCodeLength = Configuration::MINIMUM_CODE_LENGTH,
    ) {
    }

    public function generate(): string
    {
        // The configuration refuses a code_length that breaks these rules, but cannot see the value of one taken from
        // an environment variable, which is only known at runtime. Codes are bearer tokens, so the generator refuses to
        // hand out one short enough to guess, or shorter than a typed code has to be. That is checked here and not when
        // the generator is built, since the add to cart form and the order state machine callbacks build it on every
        // product page and for every order, whether a gift card is issued or not
        Assert::range($this->codeLength, Configuration::MINIMUM_CODE_LENGTH, Configuration::MAXIMUM_CODE_LENGTH, 'The code_length (%s) must be between %2$s and %3$s');
        Assert::greaterThanEq($this->codeLength, $this->minimumCodeLength, 'The code_length (%s) must be at least the minimum_code_length (%2$s)');

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
