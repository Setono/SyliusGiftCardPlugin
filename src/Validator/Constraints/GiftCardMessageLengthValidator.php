<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class GiftCardMessageLengthValidator extends ConstraintValidator
{
    public function __construct(private readonly int $maximumLength)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardMessageLength) {
            throw new UnexpectedTypeException($constraint, GiftCardMessageLength::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (mb_strlen($value) <= $this->maximumLength) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ limit }}', (string) $this->maximumLength)
            ->addViolation()
        ;
    }
}
