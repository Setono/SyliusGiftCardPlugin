<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Promotion\Checker\Rule;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Promotion\Checker\Rule\HasNoGiftCardRuleChecker;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;

final class HasNoGiftCardRuleCheckerSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(HasNoGiftCardRuleChecker::class);
    }

    public function it_is_a_promotion_rule_checker(): void
    {
        $this->shouldImplement(RuleCheckerInterface::class);
    }

    public function it_marks_orders_with_gift_card_un_eligible(
        OrderInterface $subject,
        OrderItemInterface $orderItem,
        ProductInterface $product
    ): void {
        $orderItem->getProduct()->willReturn($product);
        $product->isGiftCard()->willReturn(true);

        $subject->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $this->isEligible($subject, [])->shouldReturn(false);
    }

    public function it_marks_orders_without_gift_card_eligible(
        OrderInterface $subject,
        OrderItemInterface $orderItem,
        ProductInterface $product
    ): void {
        $orderItem->getProduct()->willReturn($product);
        $product->isGiftCard()->willReturn(false);

        $subject->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $this->isEligible($subject, [])->shouldReturn(true);
    }
}
