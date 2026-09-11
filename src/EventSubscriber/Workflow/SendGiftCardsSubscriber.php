<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber\Workflow;

use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Webmozart\Assert\Assert;

/**
 * Emails the gift cards bought in an order once it has been paid
 *
 * This mirrors the equivalent winzou callback prepended in SetonoSyliusGiftCardExtension, so the plugin
 * behaves the same whichever state machine adapter the application is configured with. Only the adapter
 * actually applying the transition emits its events, so the two can never both run
 */
final class SendGiftCardsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OrderGiftCardOperatorInterface $orderGiftCardOperator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.sylius_order_payment.completed.pay' => '__invoke',
        ];
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->orderGiftCardOperator->send($order);
    }
}
