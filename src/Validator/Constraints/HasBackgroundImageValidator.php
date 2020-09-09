<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class HasBackgroundImageValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof HasBackgroundImage) {
            throw new UnexpectedTypeException($constraint, HasBackgroundImage::class);
        }

        if (!$value instanceof GiftCardConfigurationInterface) {
            return;
        }

        $backgroundImage = $value->getBackgroundImage();

        if (null === $backgroundImage) {
            return;
        }

        if (null === $backgroundImage->getFile()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
