<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardIneligibilityReason;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class GiftCardIsEligibleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly GiftCardEligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardIsEligible) {
            throw new UnexpectedTypeException($constraint, GiftCardIsEligible::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($value, GiftCardInterface::class);
        }

        $order = $this->getCart();

        $reason = $this->eligibilityChecker->getIneligibilityReason($value, $order);
        if (null !== $reason) {
            $this->context->addViolation(match ($reason) {
                GiftCardIneligibilityReason::NotEnabled => $constraint->notEnabledMessage,
                GiftCardIneligibilityReason::Expired => $constraint->expiredMessage,
                GiftCardIneligibilityReason::NoBalance => $constraint->emptyMessage,
                GiftCardIneligibilityReason::ChannelMismatch => $constraint->channelMismatchMessage,
                GiftCardIneligibilityReason::CurrencyMismatch => $constraint->currencyMismatchMessage,
            });

            return;
        }

        if (null !== $order && $order->hasGiftCard($value)) {
            $this->context->addViolation($constraint->alreadyAppliedMessage);
        }
    }

    private function getCart(): ?OrderInterface
    {
        try {
            $cart = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return null;
        }

        return $cart instanceof OrderInterface ? $cart : null;
    }
}
