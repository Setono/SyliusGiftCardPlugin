<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Promotion\Checker\Rule;

use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Webmozart\Assert\Assert;

final class HasNoGiftCardRuleChecker implements RuleCheckerInterface
{
    public const TYPE = 'has_no_gift_card';

    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        Assert::isInstanceOf($subject, OrderInterface::class);

        $items = $subject->getItems();
        foreach ($items as $orderItem) {
            /** @var ProductInterface|null $product */
            $product = $orderItem->getProduct();
            if (null === $product) {
                // Ignore if no product
                continue;
            }

            if ($product->isGiftCard()) {
                return false;
            }
        }

        return true;
    }
}
