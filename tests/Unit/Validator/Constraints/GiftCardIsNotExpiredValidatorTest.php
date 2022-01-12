<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsNotExpired;
use Setono\SyliusGiftCardPlugin\Validator\Constraints\GiftCardIsNotExpiredValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class GiftCardIsNotExpiredValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_throws_exception_if_constraint_has_wrong_type(): void
    {
        $validator = new GiftCardIsNotExpiredValidator();
        $constraint = new NotBlank();

        $this->expectExceptionObject(new UnexpectedTypeException($constraint, GiftCardIsNotExpired::class));
        $validator->validate('super', $constraint);
    }

    /**
     * TODO: see how we can test this
     */
    public function it_returns_if_value_is_null(): void
    {
        $validator = new GiftCardIsNotExpiredValidator();
        $constraint = new GiftCardIsNotExpired();

        $value = null;
        $value->expects($this->never())->method('isExpired');
        $validator->validate($value, $constraint);
    }

    /**
     * @test
     */
    public function it_adds_violation_if_gift_card_is_expired(): void
    {
        $giftCard = $this->prophesize(GiftCardInterface::class);
        $giftCard->isExpired()->willReturn(true);

        $validator = new GiftCardIsNotExpiredValidator();
        $constraint = new GiftCardIsNotExpired();

        $executionContext = $this->prophesize(ExecutionContextInterface::class);
        $constraintViolationBuilder = $this->prophesize(ConstraintViolationBuilderInterface::class);

        $executionContext->buildViolation($constraint->message)->willReturn($constraintViolationBuilder);
        $constraintViolationBuilder->addViolation()->shouldBeCalled();

        $validator->initialize($executionContext->reveal());
        $validator->validate($giftCard->reveal(), $constraint);
    }
}
