<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Entity\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardCodeOperator;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardCodeOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardCodeRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OrderGiftCardCodeOperatorSpec extends ObjectBehavior
{
    function let(GiftCardCodeRepositoryInterface $giftCardCodeRepository, EntityManagerInterface $giftCardEntityManager): void
    {
        $this->beConstructedWith($giftCardCodeRepository, $giftCardEntityManager);
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardCodeOperator::class);
    }

    function it_implements_gift_card_code_operator_interface(): void
    {
        $this->shouldHaveType(OrderGiftCardCodeOperatorInterface::class);
    }

    function it_cancels(
        OrderInterface $order,
        GiftCardCodeRepositoryInterface $giftCardCodeRepository,
        GiftCardCodeInterface $giftCardCode,
        OrderItemInterface $orderItem
    ): void {
        $order->getItems()->willReturn(new ArrayCollection([$orderItem]));
        $giftCardCodeRepository->findBy(['orderItem' => $orderItem])->willReturn([$giftCardCode]);
        $giftCardCode->setIsActive(false)->shouldBeCalledOnce();

        $this->cancel($order);
    }
}
