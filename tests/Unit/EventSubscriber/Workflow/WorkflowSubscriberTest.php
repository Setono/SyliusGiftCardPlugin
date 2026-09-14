<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventSubscriber\Workflow;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\CommitRedemptionSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\DisableGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\EnableGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\GuardCheckoutCompletionSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\ReconcileGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\RollbackPaymentSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\RollbackRedemptionSubscriber;
use Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow\SendGiftCardsSubscriber;
use Setono\SyliusGiftCardPlugin\Guard\GiftCardCoverageGuardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Webmozart\Assert\Assert;

/**
 * These subscribers are the Symfony Workflow counterparts of the winzou callbacks prepended in
 * SetonoSyliusGiftCardExtension, so each has to forward the order to exactly the same collaborator method
 */
final class WorkflowSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider operatorSubscribers
     *
     * @param \Closure(OrderGiftCardOperatorInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_operator(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $call = $operator->__call($method, [$order]);
        Assert::isInstanceOf($call, MethodProphecy::class);
        $call->shouldBeCalledOnce();

        $subscriber = $factory($operator->reveal());
        $subscriber($this->completedEvent($order));
    }

    /**
     * @dataProvider redemptionSubscribers
     *
     * @param \Closure(GiftCardRedemptionMethodInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_redemption_method(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $call = $redemptionMethod->__call($method, [$order]);
        Assert::isInstanceOf($call, MethodProphecy::class);
        $call->shouldBeCalledOnce();

        $subscriber = $factory($redemptionMethod->reveal());
        $subscriber($this->completedEvent($order));
    }

    /** @test */
    public function it_rejects_a_subject_that_is_not_an_order(): void
    {
        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $operator->enable(Argument::cetera())->shouldNotBeCalled();

        $subscriber = new EnableGiftCardsSubscriber($operator->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $subscriber($this->completedEvent(new \stdClass()));
    }

    /** @test */
    public function it_forwards_the_refunded_payment_to_the_redemption_method(): void
    {
        $payment = $this->prophesize(PaymentInterface::class)->reveal();

        $redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $redemptionMethod->rollbackPayment($payment)->shouldBeCalledOnce();

        (new RollbackPaymentSubscriber($redemptionMethod->reveal()))($this->completedEvent($payment));
    }

    /**
     * The payment graph transitions payments, so an order arriving here means the subscriber is listening to the
     * wrong graph and must say so rather than quietly do nothing
     *
     * @test
     */
    public function it_rejects_a_subject_that_is_not_a_payment(): void
    {
        $redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $redemptionMethod->rollbackPayment(Argument::cetera())->shouldNotBeCalled();

        $subscriber = new RollbackPaymentSubscriber($redemptionMethod->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $subscriber($this->completedEvent($this->prophesize(OrderInterface::class)->reveal()));
    }

    /**
     * Disabling is hooked to a cancelled order and to one refunded in full, and deliberately not to a partial refund,
     * which does not say what it was for
     *
     * @test
     */
    public function it_disables_the_gift_cards_on_cancel_and_full_refund_but_not_on_a_partial_refund(): void
    {
        $events = array_keys(DisableGiftCardsSubscriber::getSubscribedEvents());

        self::assertContains('workflow.sylius_order.completed.cancel', $events);
        self::assertContains('workflow.sylius_order_payment.completed.refund', $events);
        self::assertNotContains('workflow.sylius_order_payment.completed.partially_refund', $events);
    }

    /**
     * @return iterable<string, array{\Closure(OrderGiftCardOperatorInterface):callable, string}>
     */
    public function operatorSubscribers(): iterable
    {
        yield 'reconcile on checkout complete' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new ReconcileGiftCardsSubscriber($operator),
            'reconcile',
        ];
        yield 'enable on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new EnableGiftCardsSubscriber($operator),
            'enable',
        ];
        yield 'send on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new SendGiftCardsSubscriber($operator),
            'send',
        ];
        yield 'disable on order cancelled' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new DisableGiftCardsSubscriber($operator),
            'disable',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(GiftCardRedemptionMethodInterface):callable, string}>
     */
    public function redemptionSubscribers(): iterable
    {
        yield 'commit on order created' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new CommitRedemptionSubscriber($method),
            'commit',
        ];
        yield 'rollback on order cancelled' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new RollbackRedemptionSubscriber($method),
            'rollback',
        ];
    }

    /** @test */
    public function it_blocks_checkout_completion_when_the_guard_is_not_satisfied(): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $guard = $this->prophesize(GiftCardCoverageGuardInterface::class);
        $guard->isSatisfiedBy($order)->willReturn(false);

        $event = $this->guardEvent($order);
        (new GuardCheckoutCompletionSubscriber($guard->reveal()))($event);

        self::assertTrue($event->isBlocked());
    }

    /** @test */
    public function it_lets_checkout_complete_when_the_guard_is_satisfied(): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $guard = $this->prophesize(GiftCardCoverageGuardInterface::class);
        $guard->isSatisfiedBy($order)->willReturn(true);

        $event = $this->guardEvent($order);
        (new GuardCheckoutCompletionSubscriber($guard->reveal()))($event);

        self::assertFalse($event->isBlocked());
    }

    private function guardEvent(object $subject): GuardEvent
    {
        return new GuardEvent($subject, new Marking(), new Transition('t', 'from', 'to'));
    }

    private function completedEvent(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('t', 'from', 'to'));
    }
}
