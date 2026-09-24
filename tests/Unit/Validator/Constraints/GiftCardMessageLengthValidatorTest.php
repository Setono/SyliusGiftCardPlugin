<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLength;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardMessageLengthValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardMessageLengthValidator>
 */
final class GiftCardMessageLengthValidatorTest extends ConstraintValidatorTestCase
{
    /** @test */
    public function it_accepts_a_message_within_the_configured_limit(): void
    {
        $this->validator->validate(str_repeat('a', 10), new GiftCardMessageLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_counts_characters_not_bytes(): void
    {
        $this->validator->validate(str_repeat('æ', 10), new GiftCardMessageLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_rejects_a_message_over_the_configured_limit(): void
    {
        $this->validator->validate(str_repeat('a', 11), new GiftCardMessageLength());

        $this->buildViolation('setono_sylius_gift_card.gift_card.custom_message.too_long')
            ->setParameter('{{ limit }}', '10')
            ->assertRaised();
    }

    /** @test */
    public function it_ignores_a_missing_message(): void
    {
        $this->validator->validate(null, new GiftCardMessageLength());
        $this->validator->validate('', new GiftCardMessageLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_strings(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new GiftCardMessageLength());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('message', new NotBlank());
    }

    protected function createValidator(): GiftCardMessageLengthValidator
    {
        return new GiftCardMessageLengthValidator(10);
    }
}
