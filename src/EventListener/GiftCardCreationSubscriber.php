<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Setono\SyliusGiftCardPlugin\Model\GiftCardInterface;
use Setono\SyliusGiftCardPlugin\Provider\GiftCardChannelConfigurationProviderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GiftCardCreationSubscriber implements EventSubscriberInterface
{
    /** @var GiftCardChannelConfigurationProviderInterface */
    private $configurationProvider;

    public function __construct(GiftCardChannelConfigurationProviderInterface $configurationProvider)
    {
        $this->configurationProvider = $configurationProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card.pre_create' => 'onGiftCardPreCreate',
        ];
    }

    public function onGiftCardPreCreate(ResourceControllerEvent $event): void
    {
        $giftCard = $event->getSubject();

        if (!$giftCard instanceof GiftCardInterface) {
            throw new UnexpectedTypeException(
                $giftCard,
                GiftCardInterface::class
            );
        }

        $configuration = $this->configurationProvider->getConfigurationForGiftCard($giftCard);
        $giftCard->setConfiguration($configuration);
    }
}
