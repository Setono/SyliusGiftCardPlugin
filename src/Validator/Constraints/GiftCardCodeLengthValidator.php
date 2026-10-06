<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Configuration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webmozart\Assert\Assert;

/**
 * A gift card code is a bearer token, and a short one can be guessed at the redemption form, where the rate limiter
 * only slows the guessing down. A generated code always meets the minimum (code_length cannot be configured below
 * it, and the code generator refuses one taken from an environment variable that is), so this is about a code an
 * admin types.
 *
 * Only a card being issued is checked. A card that exists keeps the code its customer was given, whatever its length
 * (cards brought over from 0.12 may have shorter ones), and is validated whenever it is edited, so holding it to the
 * minimum would make it impossible to edit
 */
final class GiftCardCodeLengthValidator extends ConstraintValidator
{
    public function __construct(private readonly int $minimumLength)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCodeLength) {
            throw new UnexpectedTypeException($constraint, GiftCardCodeLength::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($value, GiftCardInterface::class);
        }

        if (null !== $value->getId()) {
            return;
        }

        // A missing code is reported by the NotBlank constraint on the property, not here
        $code = $value->getCode();
        if (null === $code || '' === $code) {
            return;
        }

        // The configuration refuses a minimum_code_length outside these bounds, but cannot see the value of one taken
        // from an environment variable, which is only known at runtime
        Assert::range($this->minimumLength, Configuration::MINIMUM_CODE_LENGTH, Configuration::MAXIMUM_CODE_LENGTH, 'The minimum_code_length (%s) must be between %2$s and %3$s');

        if (mb_strlen($code) >= $this->minimumLength) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ limit }}', (string) $this->minimumLength)
            ->atPath('code')
            ->addViolation();
    }
}
