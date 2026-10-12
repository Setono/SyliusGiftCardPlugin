<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Sylius keeps every order in its channel's base currency, and a channel's other currencies only change how amounts are
 * displayed, converted with the exchange rates the shop keeps. A gift card is held in the currency it is issued in,
 * which has to be one its channel offers: the card then pays converted into the order's currency at the shop's rate
 * (#488). A card in a currency the channel does not offer could never be redeemed
 */
final class GiftCardCurrencyIsOfferedByChannelValidator extends ConstraintValidator
{
    public function __construct(
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCurrencyIsOfferedByChannel) {
            throw new UnexpectedTypeException($constraint, GiftCardCurrencyIsOfferedByChannel::class);
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

        $currencyCodes = $this->currencyProvider->getCurrencyCodes($channel);

        // A channel without a currency offers nothing to hold a card to, and has bigger problems than this card
        if ([] === $currencyCodes || in_array($currencyCode, $currencyCodes, true)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ currency }}', $currencyCode)
            ->setParameter('{{ currencies }}', implode(', ', $currencyCodes))
            ->setParameter('{{ channel }}', (string) ($channel->getName() ?? $channel->getCode()))
            ->atPath('currencyCode')
            ->addViolation();
    }
}
