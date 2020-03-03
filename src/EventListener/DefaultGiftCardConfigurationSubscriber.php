<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\EventListener;

use Setono\SyliusGiftCardPlugin\Model\GiftCardConfigurationInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DefaultGiftCardConfigurationSubscriber implements EventSubscriberInterface
{
    /** @var RepositoryInterface */
    private $giftCardConfigurationRepository;

    public function __construct(RepositoryInterface $giftCardConfigurationRepository)
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
            $currentDefault = $this->giftCardConfigurationRepository->findOneBy(['default' => true]);
            if ($currentDefault instanceof GiftCardConfigurationInterface && $currentDefault !== $giftCardConfiguration) {
                $currentDefault->setDefault(false);
            }
        }
    }
}
