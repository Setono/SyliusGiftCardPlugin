<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCodeLength;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardCodeLengthValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<GiftCardCodeLengthValidator>
 */
final class GiftCardCodeLengthValidatorTest extends ConstraintValidatorTestCase
{
    /** @test */
    public function it_accepts_a_new_card_whose_code_has_the_minimum_length(): void
    {
        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMN'), new GiftCardCodeLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_rejects_a_new_card_whose_code_is_shorter_than_the_minimum(): void
    {
        $this->validator->validate($this->newGiftCard('ABCDEFGHJKM'), new GiftCardCodeLength());

        $this->buildViolation('setono_sylius_gift_card.gift_card.code.too_short')
            ->setParameter('{{ limit }}', '12')
            ->atPath('property.path.code')
            ->assertRaised();
    }

    /** @test */
    public function it_holds_a_new_card_to_a_raised_minimum(): void
    {
        $this->validator = new GiftCardCodeLengthValidator(20);
        $this->validator->initialize($this->context);

        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMNPQRSTUVWXYZ'), new GiftCardCodeLength());
        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMNPQRSTUVW'), new GiftCardCodeLength());
        $this->assertNoViolation();

        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMNPQRSTUV'), new GiftCardCodeLength());
        $this->buildViolation('setono_sylius_gift_card.gift_card.code.too_short')
            ->setParameter('{{ limit }}', '20')
            ->atPath('property.path.code')
            ->assertRaised();
    }

    /**
     * The configuration refuses these minimums, but not a minimum_code_length taken from an environment variable,
     * whose value is only known at runtime. A card being issued is then refused rather than held to it
     *
     * @dataProvider provideMinimumLengthsTheConfigurationRefuses
     *
     * @test
     */
    public function it_refuses_to_hold_a_new_card_to_a_minimum_the_configuration_refuses(int $minimumLength, string $expectedMessage): void
    {
        $this->validator = new GiftCardCodeLengthValidator($minimumLength);
        $this->validator->initialize($this->context);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMNPQRS'), new GiftCardCodeLength());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideMinimumLengthsTheConfigurationRefuses(): iterable
    {
        yield 'guessable' => [11, 'The minimum_code_length (11) must be between 12 and 255'];
        yield 'longer than the code column' => [256, 'The minimum_code_length (256) must be between 12 and 255'];
    }

    /**
     * Cards brought over from 0.12 may have shorter codes, and they are validated whenever they are edited
     *
     * @test
     */
    public function it_leaves_the_code_of_a_card_that_exists_alone(): void
    {
        $giftCard = $this->newGiftCard('SHORT');
        (new \ReflectionProperty(GiftCard::class, 'id'))->setValue($giftCard, 1);

        $this->validator->validate($giftCard, new GiftCardCodeLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_leaves_a_missing_code_to_the_not_blank_constraint(): void
    {
        $this->validator->validate(new GiftCard(), new GiftCardCodeLength());
        $this->validator->validate($this->newGiftCard(''), new GiftCardCodeLength());
        $this->validator->validate(null, new GiftCardCodeLength());

        $this->assertNoViolation();
    }

    /** @test */
    public function it_only_validates_gift_cards(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('ABCDEFGHJKMN', new GiftCardCodeLength());
    }

    /** @test */
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->newGiftCard('ABCDEFGHJKMN'), new NotBlank());
    }

    protected function createValidator(): GiftCardCodeLengthValidator
    {
        return new GiftCardCodeLengthValidator(12);
    }

    private function newGiftCard(string $code): GiftCard
    {
        $giftCard = new GiftCard();
        $giftCard->setCode($code);

        return $giftCard;
    }
}
