<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * The cart page's twin of GiftCardFitsCartValidator: a gift card line's quantity raised there takes the cart's totals
 * past Sylius' integer columns as surely as adding the cards did.
 *
 * The cart is validated after the submitted quantities are mapped onto it and before it is processed. Sylius' quantity
 * modifier has added the units by then, and every unit added its unit price to the line's total and the cart's, so the
 * cart's totals already hold the new quantities. Only gift card lines this request raised are reported, where the
 * customer raised them: a cart that overflows through another product does so without the plugin as well.
 */
final class GiftCardQuantityFitsCartValidator extends ConstraintValidator
{
    /**
     * @param int $maximumTotal the most Sylius' order total columns hold (sylius_core.max_int_value)
     */
    public function __construct(
        private readonly int $maximumTotal,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GiftCardQuantityFitsCart) {
            throw new UnexpectedTypeException($constraint, GiftCardQuantityFitsCart::class);
        }

        if (!$value instanceof OrderInterface) {
            throw new UnexpectedValueException($value, OrderInterface::class);
        }

        if (max($value->getItemsTotal(), $value->getTotal()) <= $this->maximumTotal) {
            return;
        }

        $raisedLines = array_filter($value->getItems()->toArray(), self::isRaisedGiftCardLine(...));
        if ([] === $raisedLines) {
            return;
        }

        $maximum = $this->formatMaximum((string) $value->getCurrencyCode());

        // The cart form names a line's fields by the line's key in the cart's items
        foreach (array_keys($raisedLines) as $key) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ maximum }}', $maximum)
                ->atPath(sprintf('items[%s].quantity', $key))
                ->addViolation()
            ;
        }
    }

    /**
     * A raised line holds units without an id: the ones the quantity modifier has just added, which are not in the
     * database yet. They are the units Sylius discards when it refuses the cart (CartChangesResetter)
     */
    private static function isRaisedGiftCardLine(OrderItemInterface $item): bool
    {
        $product = $item->getProduct();
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return false;
        }

        foreach ($item->getUnits() as $unit) {
            if (null === $unit->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quoted in the locale the customer is browsing in, the way the shop quotes its prices
     */
    private function formatMaximum(string $currencyCode): string
    {
        try {
            $localeCode = $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            // Outside a shop request no locale is being browsed, and the money formatter falls back to its own
            $localeCode = null;
        }

        return $this->moneyFormatter->format($this->maximumTotal, $currencyCode, $localeCode);
    }
}
