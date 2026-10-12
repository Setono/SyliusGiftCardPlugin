<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * The add to cart form submits the currency its amount field was shown in, so a currency the customer switched to in
 * another tab since does not decide what the typed amount is worth. A submission can name any currency, though, and only
 * one the channel offers can hold a gift card
 */
final class GiftCardCurrencyIsOfferedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardCurrencyIsOffered) {
            throw new UnexpectedTypeException($constraint, GiftCardCurrencyIsOffered::class);
        }

        // Without a currency the amount is in the one the customer browses in now
        if (null === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if (!$channel instanceof ChannelInterface || in_array($value, $this->currencyProvider->getCurrencyCodes($channel), true)) {
            return;
        }

        // The value is not repeated in the message: a request can submit anything, and Sylius' add to cart script inserts
        // the message into the page as markup
        $this->context->addViolation($constraint->message);
    }
}
