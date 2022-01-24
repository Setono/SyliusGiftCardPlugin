<?php

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Validator\Constraints\Pdf;

use Prophecy\PhpUnit\ProphecyTrait;
use PHPUnit\Framework\TestCase;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\Pdf\ValidPageSize;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\Pdf\ValidPageSizeValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidPageSizeValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_constraint_has_wrong_type()
    {
        $validator = new ValidPageSizeValidator(['A0']);
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, ValidPageSize::class));
        $validator->validate('super', $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_orientation_is_invalid(): void
    {
        $validator = new ValidPageSizeValidator(['A1']);
        $constraint = new ValidPageSize();

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->message)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate('A0', $constraint);
    }
}
