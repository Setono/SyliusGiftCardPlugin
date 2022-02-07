<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Setono\SyliusGiftCardPlugin\Factory\DummyGiftCardFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardPdfGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

final class GiftCardConfigurationInitializeUpdateSubscriber implements EventSubscriberInterface
{
    private GiftCardPdfGeneratorInterface $giftCardPdfGenerator;

    private DummyGiftCardFactoryInterface $dummyGiftCardFactory;

    public function __construct(
        GiftCardPdfGeneratorInterface $giftCardPdfGenerator,
        DummyGiftCardFactoryInterface $dummyGiftCardFactory
    ) {
        $this->giftCardPdfGenerator = $giftCardPdfGenerator;
        $this->dummyGiftCardFactory = $dummyGiftCardFactory;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card_configuration.initialize_update' => 'initializeUpdate',
        ];
    }

    public function initializeUpdate(ResourceControllerEvent $event): void
    {
        $giftCardConfiguration = $event->getSubject();
        Assert::isInstanceOf($giftCardConfiguration, GiftCardConfigurationInterface::class);
        $giftCard = $this->dummyGiftCardFactory->createNew();

        $this->giftCardPdfGenerator->generateAndSavePdf($giftCard, $giftCardConfiguration);
    }
}
