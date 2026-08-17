<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.menu.admin.main' => 'addAdminMenuItems',
        ];
    }

    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $this->addCatalogChild($menu);
    }

    /**
     * Only gift cards gets a menu entry. Designs and the outstanding balance report are reachable as actions
     * on the gift cards index instead, so a single plugin does not take up three slots in the admin menu
     */
    private function addCatalogChild(ItemInterface $menu): void
    {
        $submenu = $menu->getChild('catalog');
        $item = $submenu instanceof ItemInterface ? $submenu : $menu->getFirstChild();
        $item
            ->addChild('gift_cards', [
                'route' => 'setono_sylius_gift_card_admin_gift_card_index',
            ])
            ->setLabel('setono_sylius_gift_card.ui.gift_cards')
            ->setLabelAttribute('icon', 'gift')
        ;
    }
}
