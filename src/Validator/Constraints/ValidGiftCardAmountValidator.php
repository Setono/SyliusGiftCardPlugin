<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Provider\GiftCardAmountLimitsProviderInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
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

        $limits = $this->amountLimitsProvider->getLimits($channel);
        $currencyCode = (string) $channel->getBaseCurrency()?->getCode();

        if ($value < $limits->minimum) {
            $this->context->buildViolation($constraint->tooLowMessage)
                ->setParameter('{{ minimum }}', $this->moneyFormatter->format($limits->minimum, $currencyCode))
                ->addViolation()
            ;

            return;
        }

        if (null !== $limits->maximum && $value > $limits->maximum) {
            $this->context->buildViolation($constraint->tooHighMessage)
                ->setParameter('{{ maximum }}', $this->moneyFormatter->format($limits->maximum, $currencyCode))
                ->addViolation()
            ;
        }
    }
}
