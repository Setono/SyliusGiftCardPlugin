<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

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

        /** @var GiftCardInterface $giftCard */
        $giftCard = $value;
        if ($giftCard->isExpired()) {
            $this->context->addViolation($constraint->message);
        }
    }
}
