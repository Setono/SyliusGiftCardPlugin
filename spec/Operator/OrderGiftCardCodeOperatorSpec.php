<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OrderGiftCardCodeOperatorSpec extends ObjectBehavior
{
    function let(GiftCardRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardEntityManager): void
    {
        $this->beConstructedWith($giftCardCodeRepository, $giftCardEntityManager);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardOperator::class);
    }

    function it_implements_gift_card_code_operator_interface(): void
    {
        $this->shouldHaveType(OrderGiftCardOperatorInterface::class);
    }

    function it_cancels(
        OrderInterface $order,
        GiftCardRepositoryInterface $giftCardCodeRepository,
        GiftCardInterface $giftCardCode,
        OrderItemInterface $orderItem
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem]));
        $giftCardCodeRepository->findBy(['orderItem' => $orderItem])->willReturn([$giftCardCode]);
        $giftCardCode->setActive(false)->shouldBeCalledOnce();

        $this->cancel($order);
    }
}
