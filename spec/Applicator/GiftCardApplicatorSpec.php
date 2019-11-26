<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Applicator;

use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicator;
use Setono\SyliusGiftCardPlugin\Applicator\GiftCardApplicatorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Model\OrderInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class GiftCardApplicatorSpec extends ObjectBehavior
{
    public function let(
        GiftCardRepositoryInterface $giftCardRepository,
        OrderProcessorInterface $orderProcessor,
        ObjectManager $orderManager
    ): void {
        $this->beConstructedWith($giftCardRepository, $orderProcessor, $orderManager);
    }

    public function it_implements_gift_card_applicator_interface(): void
    {
        $this->shouldImplement(GiftCardApplicatorInterface::class);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardApplicator::class);
    }

    public function it_applies(
        GiftCardRepositoryInterface $giftCardRepository,
        OrderProcessorInterface $orderProcessor,
        ObjectManager $orderManager,
        OrderInterface $order,
        GiftCardInterface $giftCard,
        ChannelInterface $channel
    ): void {
        $giftCardCode = '123';

        $giftCard->getChannel()->willReturn($channel);

        $order->getChannel()->willReturn($channel);
        $order->addGiftCard($giftCard)->shouldBeCalled();

        $giftCardRepository->findOneByCode($giftCardCode)->willReturn($giftCard);

        $orderProcessor->process($order)->shouldBeCalled();
        $orderManager->flush()->shouldBeCalled();

        $this->apply($order, $giftCardCode);
    }
}
