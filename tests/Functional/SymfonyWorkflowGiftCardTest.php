<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Order;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItem;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\OrderItemUnit;
use Setono\SyliusGiftCardPlugin\Tests\Application\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The plugin hooks the order state machines twice: winzou callbacks prepended in the extension, and the
 * Symfony Workflow listeners in EventListener/Workflow. The winzou side is exercised by the rest of the
 * suite, since winzou is the default adapter; this covers the Symfony Workflow side by driving the
 * transition through Symfony Workflow itself, which is exactly what Sylius does when an application sets
 * sylius_core.state_machine.default_adapter to symfony_workflow
 */
final class SymfonyWorkflowGiftCardTest extends GiftCardFunctionalTestCase
{
    /** @test */
    public function it_enables_gift_cards_when_symfony_workflow_pays_the_order(): void
    {
        $giftCard = $this->createPendingGiftCard();
        $order = $this->createOrderWithGiftCard($giftCard);

        self::assertFalse($giftCard->isEnabled(), 'the gift card should start out pending');

        $this->orderPaymentWorkflow()->apply($order, OrderPaymentTransitions::TRANSITION_PAY);

        self::assertTrue(
            $giftCard->isEnabled(),
            'EnableGiftCardsListener should have run off the workflow.sylius_order_payment.completed.pay event',
        );
        self::assertSame(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
    }

    private function orderPaymentWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.' . OrderPaymentTransitions::GRAPH);

        return $workflow;
    }

    private function createPendingGiftCard(): GiftCardInterface
    {
        /** @var \Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_gift_card.factory.gift_card');

        $giftCard = $factory->createNew();
        $giftCard->setCode('WORKFLOWTEST0001');
        $giftCard->setChannel($this->getChannel());
        $giftCard->setCurrencyCode('USD');
        $giftCard->setInitialAmount(5000);
        $giftCard->setAmount(5000);
        $giftCard->disable();

        $this->manager->persist($giftCard);

        return $giftCard;
    }

    /**
     * The operator walks the order's gift card items down to their units, so the whole chain has to exist:
     * a product flagged as a gift card, a variant, an item and a unit carrying the card
     */
    private function createOrderWithGiftCard(GiftCardInterface $giftCard): Order
    {
        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('GIFT_CARD_WORKFLOW');
        $product->setName('Gift card');
        $product->setSlug('gift-card-workflow');
        $product->setGiftCard(true);
        $this->manager->persist($product);

        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('GIFT_CARD_WORKFLOW_VARIANT');
        $variant->setProduct($product);
        $this->manager->persist($variant);

        $order = new Order();
        $order->setChannel($this->getChannel());
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        // pay transitions from awaiting_payment, so the order has to be there before the workflow is applied
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setUnitPrice(5000);
        $order->addItem($item);

        $unit = new OrderItemUnit($item);
        $unit->setGiftCard($giftCard);

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }
}
