<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Controller\Action\Admin\AdjustGiftCardBalanceCommand;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Deducting more than a gift card holds used to reach the balance operator, which asserts and blows up with a
 * 500, and adding more than its balance column holds made the database refuse it, with a 500 as well. Both are
 * ordinary user error, so they belong in the form as a field error
 */
final class BalanceAdjustmentIsEligibleValidator extends ConstraintValidator
{
    public function __construct(private readonly MoneyFormatterInterface $moneyFormatter)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BalanceAdjustmentIsEligible) {
            throw new UnexpectedTypeException($constraint, BalanceAdjustmentIsEligible::class);
        }

        if (!$value instanceof AdjustGiftCardBalanceCommand) {
            return;
        }

        $amount = $value->getAmount();
        if (null === $amount) {
            // NotNull reports this
            return;
        }

        $giftCard = $value->getGiftCard();
        $balance = $giftCard->getAmount();
        $currencyCode = (string) $giftCard->getCurrencyCode();

        if ($balance + $amount < 0) {
            $this->context->buildViolation($constraint->negativeBalanceMessage)
                ->setParameter('{{ balance }}', $this->moneyFormatter->format($balance, $currencyCode))
                ->atPath('amount')
                ->addViolation();

            return;
        }

        $maximum = $constraint->maximumBalance;
        if (null !== $maximum && $balance + $amount > $maximum) {
            $this->context->buildViolation($constraint->tooLargeBalanceMessage)
                ->setParameter('{{ balance }}', $this->moneyFormatter->format($balance, $currencyCode))
                ->setParameter('{{ maximum }}', $this->moneyFormatter->format($maximum, $currencyCode))
                ->atPath('amount')
                ->addViolation();
        }
    }
}
