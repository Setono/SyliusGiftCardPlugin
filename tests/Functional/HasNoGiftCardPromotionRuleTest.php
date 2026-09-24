<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Form\Type\Rule\HasNoGiftCardConfigurationType;
use Setono\SyliusGiftCardPlugin\Promotion\Checker\Rule\HasNoGiftCardRuleChecker;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionRuleType;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\Promotion;
use Sylius\Component\Promotion\Checker\Eligibility\PromotionEligibilityCheckerInterface;
use Sylius\Component\Promotion\Model\PromotionRule;
use Sylius\Component\Promotion\Model\PromotionRuleInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The rule checker and its configuration form are unit tested. This checks that Sylius picks them up from the
 * service tag: the admin offers the rule under a translated label, its promotion rule form builds and saves
 * it, and Sylius' promotion engine asks the plugin's checker when it evaluates a promotion with the rule
 */
final class HasNoGiftCardPromotionRuleTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    /** @test */
    public function the_admin_offers_the_rule_under_a_translated_label(): void
    {
        /** @var array<string, string> $rules */
        $rules = self::getContainer()->getParameter('sylius.promotion_rules');
        self::assertArrayHasKey(HasNoGiftCardRuleChecker::TYPE, $rules);

        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get('translator');

        // the rule type choice shows the label through the translator, so a key without a translation shows as is
        self::assertSame('Has no gift card', $translator->trans($rules[HasNoGiftCardRuleChecker::TYPE], [], null, 'en_US'));
    }

    /**
     * What the admin posts when a promotion gets this rule: the type, and no configuration fields, as the rule
     * takes none
     *
     * @test
     */
    public function the_admin_promotion_rule_form_builds_and_saves_the_rule(): void
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $form = $formFactory->create(PromotionRuleType::class, null, ['csrf_protection' => false]);
        $form->submit(['type' => HasNoGiftCardRuleChecker::TYPE]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertInstanceOf(
            HasNoGiftCardConfigurationType::class,
            $form->get('configuration')->getConfig()->getType()->getInnerType(),
        );

        $rule = $form->getData();
        self::assertInstanceOf(PromotionRuleInterface::class, $rule);
        self::assertSame(HasNoGiftCardRuleChecker::TYPE, $rule->getType());
        self::assertSame([], $rule->getConfiguration());
    }

    /** @test */
    public function a_promotion_with_the_rule_applies_to_an_order_without_gift_cards(): void
    {
        self::assertTrue($this->rulesEligibilityChecker()->isEligible($this->orderWith(false), $this->promotionWithTheRule()));
    }

    /** @test */
    public function a_promotion_with_the_rule_is_kept_off_an_order_that_buys_a_gift_card(): void
    {
        self::assertFalse($this->rulesEligibilityChecker()->isEligible($this->orderWith(true), $this->promotionWithTheRule()));
    }

    private function rulesEligibilityChecker(): PromotionEligibilityCheckerInterface
    {
        /** @var PromotionEligibilityCheckerInterface $checker */
        $checker = self::getContainer()->get('sylius.promotion_rules_eligibility_checker');

        return $checker;
    }

    private function promotionWithTheRule(): Promotion
    {
        $rule = new PromotionRule();
        $rule->setType(HasNoGiftCardRuleChecker::TYPE);
        $rule->setConfiguration([]);

        $promotion = new Promotion();
        $promotion->setCode('NO_GIFT_CARDS');
        $promotion->addRule($rule);

        return $promotion;
    }

    /**
     * An order holding an ordinary product and, if asked, a gift card product as well
     */
    private function orderWith(bool $giftCard): Order
    {
        $order = new Order();
        $order->addItem($this->itemOf(false));

        if ($giftCard) {
            $order->addItem($this->itemOf(true));
        }

        return $order;
    }

    private function itemOf(bool $giftCard): OrderItem
    {
        $product = new Product();
        $product->setGiftCard($giftCard);

        $variant = new ProductVariant();
        $variant->setProduct($product);

        $item = new OrderItem();
        $item->setVariant($variant);

        return $item;
    }
}
