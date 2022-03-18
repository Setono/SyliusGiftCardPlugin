<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Validator\Constraints\Pdf;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Provider\PdfRenderingOptionsProviderInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\Pdf\ValidOrientation;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\Pdf\ValidOrientationValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidOrientationValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_constraint_has_wrong_type(): void
    {
        $validator = new ValidOrientationValidator([PdfRenderingOptionsProviderInterface::ORIENTATION_PORTRAIT]);
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, ValidOrientation::class));
        $validator->validate('Any orientation', $constraint);
    }

    /**
     * @test
     */
    public function it_does_nothing_if_value_is_null(): void
    {
        $validator = new ValidOrientationValidator([PdfRenderingOptionsProviderInterface::ORIENTATION_PORTRAIT]);
        $constraint = new ValidOrientation();

        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $constraintViolationBuilder->addViolation()->shouldNotBeCalled();

        $validator->validate(null, $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_orientation_is_invalid(): void
    {
        $validator = new ValidOrientationValidator([PdfRenderingOptionsProviderInterface::ORIENTATION_PORTRAIT]);
        $constraint = new ValidOrientation();

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->message)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate('Invalid orientation', $constraint);
    }
}
