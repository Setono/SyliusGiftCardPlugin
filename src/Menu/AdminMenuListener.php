<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addAdminMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $submenu = $menu->getChild('marketing');
        if ($submenu instanceof ItemInterface) {
            $this->addChild($submenu);
        } else {
            $this->addChild($menu->getFirstChild());
        }
    }

    private function addChild(ItemInterface $item): void
    {
        $item
            ->addChild('gift_cards', [
                'route' => 'setono_sylius_gift_card_admin_gift_card_index',
            ])
            ->setLabel('setono_sylius_gift_card.ui.gift_cards')
            ->setLabelAttribute('icon', 'gift')
        ;
    }
}
