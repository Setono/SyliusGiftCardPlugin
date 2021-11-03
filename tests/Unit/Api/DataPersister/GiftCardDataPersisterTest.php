<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\Api\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Api\DataPersister\GiftCardDataPersister;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;

final class GiftCardDataPersisterTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_supports_gift_cards(): void
    {
        $decoratedDataPersister = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $dataPersister = new GiftCardDataPersister($decoratedDataPersister->reveal());

        $this->assertTrue($dataPersister->supports(new GiftCard()));
    }

    /**
     * @test
     */
    public function it_removes_data(): void
    {
        $decoratedDataPersister = $this->prophesize(ContextAwareDataPersisterInterface::class);
        $dataPersister = new GiftCardDataPersister($decoratedDataPersister->reveal());

        $data = new GiftCard();
        $context = [];
        $decoratedDataPersister->remove($data, $context)->shouldBeCalled();
        $dataPersister->remove($data, $context);
    }

    /**
     * @test
     */
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
