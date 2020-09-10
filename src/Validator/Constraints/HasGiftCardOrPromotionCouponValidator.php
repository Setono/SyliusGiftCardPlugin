<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Controller\Action\AddGiftCardOrPromotionCouponToOrderCommand;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webmozart\Assert\Assert;

final class HasGiftCardOrPromotionCouponValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof HasGiftCardOrPromotionCoupon) {
            throw new UnexpectedTypeException($constraint, HasGiftCardOrPromotionCoupon::class);
        }

        if (null === $value) {
            return;
        }

        Assert::isInstanceOf($value, AddGiftCardOrPromotionCouponToOrderCommand::class);

        if (null === $value->getGiftCard() && null === $value->getPromotionCoupon()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('giftCardOrPromotion')
                ->addViolation();
        }
    }
}
