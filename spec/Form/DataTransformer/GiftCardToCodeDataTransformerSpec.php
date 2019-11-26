<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Form\DataTransformer;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Form\DataTransformer\GiftCardToCodeDataTransformer;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Form\DataTransformerInterface;

final class GiftCardToCodeDataTransformerSpec extends ObjectBehavior
{
    public function let(
        GiftCardRepositoryInterface $giftCardRepository,
        ChannelContextInterface $channelContext
    ): void {
        $this->beConstructedWith($giftCardRepository, $channelContext);
    }

    public function it_implements_data_transformer_interface(): void
    {
        $this->shouldImplement(DataTransformerInterface::class);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(GiftCardToCodeDataTransformer::class);
    }

    public function it_transforms(GiftCardInterface $giftCard): void
    {
        $giftCard->getCode()->willReturn('code');

        $this->transform($giftCard)->shouldReturn('code');
    }

    public function it_reverse_transforms(
        GiftCardRepositoryInterface $giftCardRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel,
        GiftCardInterface $giftCard
    ): void {
        $code = '123';

        $channelContext->getChannel()->willReturn($channel);

        $giftCardRepository->findOneEnabledByCodeAndChannel($code, $channel)->willReturn($giftCard);

        $this->reverseTransform($code)->shouldReturn($giftCard);
    }
}
