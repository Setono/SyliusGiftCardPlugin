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
 * 500. It is ordinary user error, so it belongs in the form as a field error
 */
final class BalanceAdjustmentIsApplicableValidator extends ConstraintValidator
{
    public function __construct(private readonly MoneyFormatterInterface $moneyFormatter)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BalanceAdjustmentIsApplicable) {
            throw new UnexpectedTypeException($constraint, BalanceAdjustmentIsApplicable::class);
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
        if ($balance + $amount >= 0) {
            return;
        }

        $this->context->buildViolation($constraint->negativeBalanceMessage)
            ->setParameter('{{ balance }}', $this->moneyFormatter->format($balance, (string) $giftCard->getCurrencyCode()))
            ->atPath('amount')
            ->addViolation();
    }
}
