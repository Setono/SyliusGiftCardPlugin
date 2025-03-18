<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Api\DataPersister;

use PHPUnit\Framework\Attributes\Test;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\DataPersister\GiftCardDataPersister;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

final class GiftCardDataPersisterTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_supports_gift_cards(): void
    {
        $decoratedDataPersister = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $dataPersister = new GiftCardDataPersister($decoratedDataPersister->reveal());

        $this->assertTrue($dataPersister->supports(new GiftCard()));
    }

    #[Test]
    public function it_removes_data(): void
    {
        $decoratedDataPersister = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $dataPersister = new GiftCardDataPersister($decoratedDataPersister->reveal());

        $data = new GiftCard();
        $context = [];
        $decoratedDataPersister->remove($data, $context)->shouldBeCalled();
        $dataPersister->remove($data, $context);
    }

    #[Test]
    public function it_persists_data(): void
    {
        $decoratedDataPersister = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $dataPersister = new GiftCardDataPersister($decoratedDataPersister->reveal());

        $data = new GiftCard();
        $context = [];
        $decoratedDataPersister->persist($data, $context)->shouldBeCalled();
        $decoratedDataPersister->persist($data, $context)->willReturn($data);
        /** @var GiftCardInterface $giftCard */
        $giftCard = $dataPersister->persist($data, $context);

        $this->assertSame(GiftCardInterface::ORIGIN_API, $giftCard->getOrigin());
    }
}
