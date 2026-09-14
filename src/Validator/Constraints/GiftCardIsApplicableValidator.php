<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        private readonly LoggerInterface $logger = new NullLogger(),
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
            $this->reject($value, $constraint, 'it is not enabled');

            return;
        }

        if ($value->isExpired()) {
            $this->reject($value, $constraint, 'it is expired');

            return;
        }

        if ($value->getAmount() <= 0) {
            $this->reject($value, $constraint, 'it has no balance left');

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
            $this->reject($value, $constraint, sprintf('it does not belong to the channel "%s"', (string) $orderChannel->getCode()));

            return;
        }

        $orderCurrencyCode = $order->getCurrencyCode();
        if (null !== $orderCurrencyCode && $value->getCurrencyCode() !== $orderCurrencyCode) {
            $this->reject($value, $constraint, sprintf('it is not in the order currency "%s"', $orderCurrencyCode));
        }
    }

    /**
     * Adds the generic violation the customer gets to see and keeps the actual reason in the log, where it
     * helps the shop owner without telling a code guesser that they hit an existing code
     */
    private function reject(GiftCardInterface $giftCard, GiftCardIsApplicable $constraint, string $reason): void
    {
        $this->logger->info(sprintf(
            'The gift card "%s" was not applied to the order because %s',
            (string) $giftCard->getCode(),
            $reason,
        ));

        $this->context->addViolation($constraint->message);
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
