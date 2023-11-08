<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webmozart\Assert\Assert;

final class DatePeriodValidator extends ConstraintValidator
{
    private DatePeriodUnitProviderInterface $datePeriodUnitProvider;

    public function __construct(DatePeriodUnitProviderInterface $datePeriodUnitProvider)
    {
        $this->datePeriodUnitProvider = $datePeriodUnitProvider;
    }

    /**
     * @param mixed $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DatePeriod) {
            throw new UnexpectedTypeException($constraint, DatePeriod::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        Assert::string($value);
        [$duration, $unit] = \explode(' ', $value);
        if ((int) $duration <= 0) {
            $this->context->buildViolation($constraint->invalidDuration)->addViolation();
        }

        if (!\in_array($unit, $this->datePeriodUnitProvider->getPeriodUnits())) {
            $this->context->buildViolation($constraint->invalidUnit)->addViolation();
        }
    }
}
