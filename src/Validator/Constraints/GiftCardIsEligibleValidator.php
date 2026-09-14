<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusGiftCardPlugin\Checker\GiftCardEligibilityCheckerInterface;
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
        private readonly LoggerInterface $logger = new NullLogger(),
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
            // The customer gets the one generic message whatever the reason, because telling the reasons apart
            // would tell a code guesser that the code exists. The reason goes to the log instead, where it helps
            // the shop owner answer "why does my gift card not work"
            $this->logger->info(sprintf(
                'The gift card "%s" was not applied to the order: %s',
                (string) $value->getCode(),
                $reason->value,
            ));

            $this->context->addViolation($constraint->message);

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
