<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\DatePeriodUnitProvider;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\DatePeriod;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\DatePeriodValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class DatePeriodValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_constraint_has_wrong_type(): void
    {
        $validator = new DatePeriodValidator(new DatePeriodUnitProvider());
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, DatePeriod::class));
        $validator->validate('super', $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_duration_is_negative(): void
    {
        $validator = new DatePeriodValidator(new DatePeriodUnitProvider());
        $constraint = new DatePeriod();

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->invalidDuration)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate('-8 month', $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_unit_is_invalid(): void
    {
        $validator = new DatePeriodValidator(new DatePeriodUnitProvider());
        $constraint = new DatePeriod();

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->invalidUnit)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate('8 blob', $constraint);
    }
}
