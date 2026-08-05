<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\EventListener\Workflow;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\CommitRedemptionListener;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\DisableGiftCardsListener;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\EnableGiftCardsListener;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\ReconcileGiftCardsListener;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\RollbackRedemptionListener;
use Setono\SyliusGiftCardPlugin\EventListener\Workflow\SendGiftCardsListener;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Redemption\GiftCardRedemptionMethodInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

/**
 * These listeners are the Symfony Workflow counterparts of the winzou callbacks prepended in
 * SetonoSyliusGiftCardExtension, so each has to forward the order to exactly the same collaborator method
 */
final class WorkflowListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider operatorListeners
     *
     * @param \Closure(OrderGiftCardOperatorInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_operator(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $operator->{$method}($order)->shouldBeCalledOnce();

        $listener = $factory($operator->reveal());
        $listener($this->completedEvent($order));
    }

    /**
     * @dataProvider redemptionListeners
     *
     * @param \Closure(GiftCardRedemptionMethodInterface):callable $factory
     *
     * @test
     */
    public function it_forwards_the_order_to_the_redemption_method(\Closure $factory, string $method): void
    {
        $order = $this->prophesize(OrderInterface::class)->reveal();

        $redemptionMethod = $this->prophesize(GiftCardRedemptionMethodInterface::class);
        $redemptionMethod->{$method}($order)->shouldBeCalledOnce();

        $listener = $factory($redemptionMethod->reveal());
        $listener($this->completedEvent($order));
    }

    /** @test */
    public function it_rejects_a_subject_that_is_not_an_order(): void
    {
        $operator = $this->prophesize(OrderGiftCardOperatorInterface::class);
        $operator->enable(\Prophecy\Argument::cetera())->shouldNotBeCalled();

        $listener = new EnableGiftCardsListener($operator->reveal());

        $this->expectException(\InvalidArgumentException::class);

        $listener($this->completedEvent(new \stdClass()));
    }

    /**
     * @return iterable<string, array{\Closure(OrderGiftCardOperatorInterface):callable, string}>
     */
    public function operatorListeners(): iterable
    {
        yield 'reconcile on checkout complete' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new ReconcileGiftCardsListener($operator),
            'reconcile',
        ];
        yield 'enable on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new EnableGiftCardsListener($operator),
            'enable',
        ];
        yield 'send on order paid' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new SendGiftCardsListener($operator),
            'send',
        ];
        yield 'disable on order cancelled' => [
            static fn (OrderGiftCardOperatorInterface $operator): callable => new DisableGiftCardsListener($operator),
            'disable',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(GiftCardRedemptionMethodInterface):callable, string}>
     */
    public function redemptionListeners(): iterable
    {
        yield 'commit on order created' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new CommitRedemptionListener($method),
            'commit',
        ];
        yield 'rollback on order cancelled' => [
            static fn (GiftCardRedemptionMethodInterface $method): callable => new RollbackRedemptionListener($method),
            'rollback',
        ];
    }

    private function completedEvent(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('t', 'from', 'to'));
    }
}
