<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Assigner;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Assigner\OrderGiftCardCodeAssigner;
use Setono\SyliusGiftCardPlugin\Assigner\OrderGiftCardCodeAssignerInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardCodeInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Doctrine\ORM\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardProductResolverInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;

final class OrderGiftCardCodeAssignerSpec extends ObjectBehavior
{
    function let(
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager,
        EntityManagerInterface $giftCardEntityManager
    ): void {
        $this->beConstructedWith(
            $giftCardCodeFactory,
            $giftCardCodeGenerator,
            $giftCardRepository,
            $giftCardOrderEmailManager,
            $giftCardEntityManager
        );
    }

    function it_is_initializable(): void
    {
        $this->shouldHaveType(OrderGiftCardCodeAssigner::class);
    }

    function it_implements_order_gift_card_code_assigner_interface(): void
    {
        $this->shouldHaveType(OrderGiftCardCodeAssignerInterface::class);
    }

    function it_assigns_gift_card_code(
        OrderInterface $order,
        OrderItemInterface $orderItem,
        ProductInterface $product,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardInterface $giftCard,
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        GiftCardCodeInterface $giftCardCode,
        ChannelInterface $channel,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        EntityManager $giftCardEntityManager,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager,
        CurrencyInterface $baseCurrency
    ): void {
        $giftCardCodeGenerator->generate()->willReturn('fehfekf');

        $baseCurrency->getCode()->willReturn('USD');

        $channel->getCode()->willReturn('WEB');
        $channel->getBaseCurrency()->willReturn($baseCurrency);

        $orderItem->getProduct()->willReturn($product);
        $orderItem->getQuantity()->willReturn(2);
        $orderItem->getUnitPrice()->willReturn(100);

        $order->getItems()->willReturn(new ArrayCollection([$orderItem->getWrappedObject()]));
        $order->getChannel()->willReturn($channel);

        $giftCardRepository->findOneByProduct($product)->willReturn($giftCard);
        $giftCardCodeFactory->createForGiftCardAndOrderItem($giftCard, $orderItem)->willReturn($giftCardCode);

        $giftCardCode->setAmount(100)->shouldBeCalledTimes(2);
        $giftCardCode->setCurrencyCode('USD')->shouldBeCalledTimes(2);
        $giftCardCode->setChannel($channel)->shouldBeCalledTimes(2);
        $giftCardCode->setCode('fehfekf')->shouldBeCalledTimes(2);
        $giftCardCode->setActive(true)->shouldBeCalledTimes(2);
        $giftCardEntityManager->persist($giftCardCode)->shouldBeCalledTimes(2);
        $giftCardEntityManager->flush()->shouldBeCalledTimes(2);
        $giftCardOrderEmailManager->sendEmailWithGiftCardCodes($order, [$giftCardCode, $giftCardCode])->shouldBeCalled();

        $this->assignGiftCardCode($order);
    }
}
