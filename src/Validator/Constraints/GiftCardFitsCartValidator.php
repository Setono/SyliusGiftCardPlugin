<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Validator\Constraints;

use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Order\AddToCartCommandInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Sylius keeps an order's totals in integer columns. The admin sets the prices of a shop's products, but the customer
 * chooses a gift card's: two large cards, or one at a quantity of two, took the cart past what the columns hold, and
 * the database refused the cart with a 500.
 *
 * The cart is not processed yet when the command is validated, so this adds the line to the totals the cart was last
 * processed to: its unit price is the amount, as no promotion discounts a gift card. What processing adds besides (a
 * tax on the gift card product, shipping for a physical card) is not known yet, and is left out.
 */
final class GiftCardFitsCartValidator extends ConstraintValidator
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
        if (!$constraint instanceof GiftCardFitsCart) {
            throw new UnexpectedTypeException($constraint, GiftCardFitsCart::class);
        }

        if (!$value instanceof AddToCartCommandInterface) {
            throw new UnexpectedValueException($value, AddToCartCommandInterface::class);
        }

        $cartItem = $value->getCartItem();
        // Through the variant, as Sylius' order item reads its product off a variant it assumes it has. A line without
        // one is CartItemVariantRequired's to report
        $product = $cartItem->getVariant()?->getProduct();
        if (!$product instanceof ProductInterface || !$product->isGiftCard()) {
            return;
        }

        $amount = $value->getGiftCardInformation()->getAmount();

        // A blank amount is the amount field's to report. So is an amount no line's unit price can hold, which is
        // about the card and not about the cart: reporting it here as well would tell the customer twice
        if (null === $amount || $amount > $this->maximumTotal) {
            return;
        }

        $cart = $value->getCart();

        // An order discount or shipping keeps the total apart from the items total, so the larger of the two is the
        // one to hold. The line adds the same to both
        $largestTotal = max($cart->getItemsTotal(), $cart->getTotal()) + $amount * $cartItem->getQuantity();
        if ($largestTotal <= $this->maximumTotal) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ maximum }}', $this->formatMaximum((string) $cart->getCurrencyCode()))
            ->addViolation()
        ;
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
