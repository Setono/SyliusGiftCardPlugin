<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Sylius keeps every order amount in the channel's base currency; the other currencies a channel offers only
 * change how amounts are displayed. A gift card's balance is compared one to one with those order amounts, so
 * the only currency in which a card can be issued correctly is the base currency of its channel.
 */
final class GiftCardCurrencyIsChannelBaseCurrencyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCurrencyIsChannelBaseCurrency) {
            throw new UnexpectedTypeException($constraint, GiftCardCurrencyIsChannelBaseCurrency::class);
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

        $baseCurrencyCode = $channel->getBaseCurrency()?->getCode();
        if (null === $baseCurrencyCode || $baseCurrencyCode === $currencyCode) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ currency }}', $currencyCode)
            ->setParameter('{{ base_currency }}', $baseCurrencyCode)
            ->setParameter('{{ channel }}', (string) ($channel->getName() ?? $channel->getCode()))
            ->atPath('currencyCode')
            ->addViolation();
    }
}
