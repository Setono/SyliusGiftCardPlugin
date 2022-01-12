<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webmozart\Assert\Assert;

final class GiftCardIsNotExpiredValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardIsNotExpired) {
            throw new UnexpectedTypeException($constraint, GiftCardIsNotExpired::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        Assert::isInstanceOf($value, GiftCardInterface::class);
        if ($value->isExpired()) {
            $this->context->addViolation($constraint->message);
        }
    }
}
