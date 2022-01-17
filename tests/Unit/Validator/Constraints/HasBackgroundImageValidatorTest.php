<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationImage;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\HasBackgroundImage;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\HasBackgroundImageValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class HasBackgroundImageValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_constraint_has_wrong_type(): void
    {
        $validator = new HasBackgroundImageValidator();
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, HasBackgroundImage::class));
        $validator->validate(new GiftCardConfiguration(), $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_no_image_has_been_set(): void
    {
        $validator = new HasBackgroundImageValidator();
        $constraint = new HasBackgroundImage();

        $value = new GiftCardConfiguration();
        $backgroundImage = new GiftCardConfigurationImage();
        $value->setBackgroundImage($backgroundImage);

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->message)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate($value, $constraint);
    }
}
