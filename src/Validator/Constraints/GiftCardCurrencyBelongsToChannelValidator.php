<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A gift card can only be redeemed on an order in its own currency, and an order is always in one of its channel's
 * currencies, so a card whose currency the channel does not offer could never be spent.
 */
final class GiftCardCurrencyBelongsToChannelValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCurrencyBelongsToChannel) {
            throw new UnexpectedTypeException($constraint, GiftCardCurrencyBelongsToChannel::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof GiftCardInterface) {
            throw new UnexpectedTypeException($value, GiftCardInterface::class);
        }

        $currencyCode = $value->getCurrencyCode();
        $channel = $value->getChannel();

        // A missing currency or channel is reported by the constraints on those properties, not here
        if (null === $currencyCode || '' === $currencyCode || null === $channel) {
            return;
        }

        if ($channel->getBaseCurrency()?->getCode() === $currencyCode) {
            return;
        }

        foreach ($channel->getCurrencies() as $currency) {
            if ($currency->getCode() === $currencyCode) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ currency }}', $currencyCode)
            ->setParameter('{{ channel }}', (string) ($channel->getName() ?? $channel->getCode()))
            ->atPath('currencyCode')
            ->addViolation();
    }
}
