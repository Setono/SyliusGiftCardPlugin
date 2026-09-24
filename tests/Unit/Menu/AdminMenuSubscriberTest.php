<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Menu;

use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Menu\AdminMenuSubscriber;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The plugin takes a single entry in the admin menu, under the catalog, where a merchant looks for what the shop sells
 */
final class AdminMenuSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @test */
    public function it_subscribes_to_the_building_of_the_admin_main_menu(): void
    {
        self::assertSame(['sylius.menu.admin.main' => 'addAdminMenuItems'], AdminMenuSubscriber::getSubscribedEvents());
    }

    /** @test */
    public function it_adds_gift_cards_to_the_catalog(): void
    {
        $factory = $this->menuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('sales');
        $catalog = $menu->addChild('catalog');

        (new AdminMenuSubscriber())->addAdminMenuItems(new MenuBuilderEvent($factory, $menu));

        self::assertSame(['sales', 'catalog'], array_keys($menu->getChildren()), 'nothing is added next to the catalog');
        self::assertSame(['gift_cards'], array_keys($catalog->getChildren()));

        $item = $catalog->getChild('gift_cards');
        self::assertInstanceOf(ItemInterface::class, $item);
        self::assertSame('setono_sylius_gift_card.ui.gift_cards', $item->getLabel());
        self::assertSame('gift', $item->getLabelAttribute('icon'));
        self::assertSame('/admin/gift-cards/', $item->getUri());
    }

    /**
     * A menu without a catalog section still gets the entry, in its first section
     *
     * @test
     */
    public function it_falls_back_to_the_first_section_when_there_is_no_catalog(): void
    {
        $factory = $this->menuFactory();
        $menu = $factory->createItem('root');
        $first = $menu->addChild('sales');
        $second = $menu->addChild('customers');

        (new AdminMenuSubscriber())->addAdminMenuItems(new MenuBuilderEvent($factory, $menu));

        self::assertSame(['gift_cards'], array_keys($first->getChildren()));
        self::assertSame([], $second->getChildren());
    }

    /**
     * A factory resolving routes the way the admin menu's does
     */
    private function menuFactory(): MenuFactory
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_gift_card_admin_gift_card_index', [], Argument::any())->willReturn('/admin/gift-cards/');

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator->reveal()));

        return $factory;
    }
}
