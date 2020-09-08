<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Operator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderItemUnitInterface;
use Setono\SyliusGiftCardPlugin\Model\ProductInterface;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperator;
use Setono\SyliusGiftCardPlugin\Operator\OrderGiftCardOperatorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

class OrderGiftCardOperatorSpec extends ObjectBehavior
{
    public function let(
        GiftCardFactoryInterface $giftCardFactory,
        GiftCardRepositoryInterface $giftCardRepository,
        EntityManagerInterface $giftCardManager,
        GiftCardEmailManagerInterface $giftCardOrderEmailManager
    ): void {
        $this->beConstructedWith(
            $giftCardFactory,
            $giftCardRepository,
            $giftCardManager,
            $giftCardOrderEmailManager
        );
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardOperator::class);
    }

    public function it_implements_order_gift_card_operator_interface(): void
    {
        $this->shouldImplement(OrderGiftCardOperatorInterface::class);
    }

    public function it_creates_disabled_gift_cards(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        ProductInterface $product,
        OrderItemUnitInterface $orderItemUnit,
        GiftCardInterface $giftCard,
        GiftCardFactoryInterface $giftCardFactory,
        EntityManagerInterface $giftCardManager
    ): void {
        $product->isGiftCard()->willReturn(true);

        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->getWrappedObject()]));
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));

        $giftCardFactory->createFromOrderItemUnit($orderItemUnit)->willReturn($giftCard);

        $giftCardManager->persist($giftCard)->shouldBeCalled();
        $giftCardManager->flush()->shouldBeCalled();

        $this->create($order);
    }

    public function it_enables_gift_cards_on_given_order(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        ProductInterface $product,
        OrderItemUnitInterface $orderItemUnit,
        GiftCardInterface $giftCard,
        GiftCardRepositoryInterface $giftCardRepository,
        EntityManagerInterface $giftCardManager
    ): void {
        $product->isGiftCard()->willReturn(true);

        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->getWrappedObject()]));
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));

        $giftCardRepository->findOneByOrderItemUnit($orderItemUnit)->willReturn($giftCard);

        $giftCard->enable()->shouldBeCalled();

        $giftCardManager->flush()->shouldBeCalled();

        $this->enable($order);
    }

    public function it_disables_gift_cards_on_given_order(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        ProductInterface $product,
        OrderItemUnitInterface $orderItemUnit,
        GiftCardInterface $giftCard,
        GiftCardRepositoryInterface $giftCardRepository,
        EntityManagerInterface $giftCardManager
    ): void {
        $product->isGiftCard()->willReturn(true);

        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->getWrappedObject()]));
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));

        $giftCardRepository->findOneByOrderItemUnit($orderItemUnit)->willReturn($giftCard);

        $giftCard->disable()->shouldBeCalled();

        $giftCardManager->flush()->shouldBeCalled();

        $this->disable($order);
    }

    public function it_sends_gift_cards_on_given_order(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        ProductInterface $product,
        OrderItemUnitInterface $orderItemUnit,
        GiftCardInterface $giftCard,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardEmailManagerInterface $giftCardOrderEmailManager
    ): void {
        $product->isGiftCard()->willReturn(true);

        $orderItem->getProduct()->willReturn($product);
        $orderItem->getUnits()->willReturn(new ArrayCollection([$orderItemUnit->getWrappedObject()]));
        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));

        $giftCardRepository->findOneByOrderItemUnit($orderItemUnit)->willReturn($giftCard);

        $giftCardOrderEmailManager->sendEmailWithGiftCardsFromOrder($order, [$giftCard])->shouldBeCalled();

        $this->send($order);
    }
}
