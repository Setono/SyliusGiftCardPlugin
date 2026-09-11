<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class GiftCardIsApplicableValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardIsApplicable) {
            throw new UnexpectedTypeException($constraint, GiftCardIsApplicable::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($value, GiftCardInterface::class);
        }

        if (!$value->isEnabled()) {
            $this->context->addViolation($constraint->notEnabledMessage);

            return;
        }

        if ($value->isExpired()) {
            $this->context->addViolation($constraint->expiredMessage);

            return;
        }

        if ($value->getAmount() <= 0) {
            $this->context->addViolation($constraint->emptyMessage);

            return;
        }

        $order = $this->getCart();
        if (!$order instanceof OrderInterface) {
            return;
        }

        if ($order->hasGiftCard($value)) {
            $this->context->addViolation($constraint->alreadyAppliedMessage);

            return;
        }

        $orderChannel = $order->getChannel();
        if (null !== $orderChannel && $value->getChannel()?->getCode() !== $orderChannel->getCode()) {
            $this->context->addViolation($constraint->channelMismatchMessage);

            return;
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null !== $orderCurrencyCode && $value->getCurrencyCode() !== $orderCurrencyCode) {
            $this->context->addViolation($constraint->currencyMismatchMessage);
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
