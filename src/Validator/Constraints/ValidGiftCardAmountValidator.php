<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Order\GiftCardInformationInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardCurrencyProviderInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidGiftCardAmountValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly GiftCardAmountLimitsProviderInterface $amountLimitsProvider,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly LocaleContextInterface $localeContext,
        private readonly GiftCardCurrencyProviderInterface $currencyProvider,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidGiftCardAmount) {
            throw new UnexpectedTypeException($constraint, ValidGiftCardAmount::class);
        }

        if (null === $value) {
            return;
        }

        if (!is_int($value)) {
            throw new UnexpectedValueException($value, 'int');
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return;
        }

        if (!$channel instanceof ChannelInterface) {
            return;
        }

        // The amount is in the currency the card is held in, and held to the limits in that currency without conversion.
        // The form submits that currency, so it can be anything a request says until GiftCardCurrencyIsOffered has
        // refused it, which does not keep this from running: a currency the channel does not offer is not taken, or the
        // money formatter would fail on a code it cannot format
        $information = $this->context->getObject();
        $currencyCode = $information instanceof GiftCardInformationInterface ? $information->getCurrencyCode() : null;
        if (null === $currencyCode || !in_array($currencyCode, $this->currencyProvider->getCurrencyCodes($channel), true)) {
            $currencyCode = $this->currencyProvider->getCurrencyCode($channel);
        }

        $limits = $this->amountLimitsProvider->getLimits($channel, $currencyCode);

        if ($value < $limits->minimum) {
            $this->context->buildViolation($constraint->tooLowMessage)
                ->setParameter('{{ minimum }}', $this->formatLimit($limits->minimum, $currencyCode))
                ->addViolation()
            ;

            return;
        }

        if (null !== $limits->maximum && $value > $limits->maximum) {
            $this->context->buildViolation($constraint->tooHighMessage)
                ->setParameter('{{ maximum }}', $this->formatLimit($limits->maximum, $currencyCode))
                ->addViolation()
            ;
        }
    }

    /**
     * The amount field's help text quotes the same limits before the customer submits (GiftCardInformationType), in
     * the locale the customer is browsing in, so the error quotes them that way too rather than in English
     */
    private function formatLimit(int $limit, string $currencyCode): string
    {
        try {
            $localeCode = $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            // Validation also runs outside a shop request (a console command, a host application's API), where no
            // locale is being browsed. The money formatter then falls back to its own default instead of the
            // validation failing
            $localeCode = null;
        }

        return $this->moneyFormatter->format($limit, $currencyCode, $localeCode);
    }
}
