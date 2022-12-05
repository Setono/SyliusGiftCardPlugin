<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventSubscriber;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardConfigurationRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DefaultGiftCardConfigurationSubscriber implements EventSubscriberInterface
{
    private GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository;

    public function __construct(GiftCardConfigurationRepositoryInterface $giftCardConfigurationRepository)
    {
        $this->giftCardConfigurationRepository = $giftCardConfigurationRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_gift_card.gift_card_configuration.pre_update' => 'preUpdate',
        ];
    }

    public function preUpdate(ResourceControllerEvent $event): void
    {
        $giftCardConfiguration = $event->getSubject();
        if (!$giftCardConfiguration instanceof GiftCardConfigurationInterface) {
            throw new UnexpectedTypeException($giftCardConfiguration, GiftCardConfigurationInterface::class);
        }

        if ($giftCardConfiguration->isDefault()) {
            /** @var GiftCardConfigurationInterface|null $currentDefaultGiftCardConfiguration */
            $currentDefaultGiftCardConfiguration = $this->giftCardConfigurationRepository->findOneBy(['default' => true]);
            if ($currentDefaultGiftCardConfiguration instanceof GiftCardConfigurationInterface && $currentDefaultGiftCardConfiguration->getId() !== $giftCardConfiguration->getId()) {
                $currentDefaultGiftCardConfiguration->setDefault(false);
            }
        }
    }
}
