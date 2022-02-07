<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusGiftCardPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\EventListener\GiftCardConfigurationInitializeUpdateSubscriber;
use Setono\SyliusGiftCardPlugin\Factory\DummyGiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCard;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfiguration;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;

final class GiftCardConfigurationInitializeUpdateSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_asks_pdf_generation(): void
    {
        $giftCardConfiguration = new GiftCardConfiguration();
        $event = new ResourceControllerEvent($giftCardConfiguration);
        $dummyFactory = $this->prophesize(DummyGiftCardFactoryInterface::class);
        $giftCard = new GiftCard();
        $dummyFactory->createNew()->willReturn($giftCard);

        $giftCardPdfGenerator = $this->prophesize(GiftCardPdfGeneratorInterface::class);
        $giftCardPdfGenerator->generateAndSavePdf($giftCard, $giftCardConfiguration)->shouldBeCalled();

        $giftCardConfigurationInitializeUpdateSubscriber = new GiftCardConfigurationInitializeUpdateSubscriber(
            $giftCardPdfGenerator->reveal(),
            $dummyFactory->reveal()
        );
        $giftCardConfigurationInitializeUpdateSubscriber->initializeUpdate($event);
    }
}
