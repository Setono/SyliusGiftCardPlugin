<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A shop whose URLs carry no locale imports routes_no_locale.yaml instead of routes.yaml, as the READMEs of 0.11 and
 * 0.12 told it to. The file has to register the routes routes.yaml does: the shop routes without the /{_locale} prefix,
 * since such a shop has no locale to put in its URLs, and the admin routes where routes.yaml puts them
 */
final class RoutesWithoutLocaleTest extends KernelTestCase
{
    private const WITH_LOCALE = '@SetonoSyliusGiftCardPlugin/Resources/config/routes.yaml';

    private const WITHOUT_LOCALE = '@SetonoSyliusGiftCardPlugin/Resources/config/routes_no_locale.yaml';

    private const SHOP_ROUTE_PREFIX = 'setono_sylius_gift_card_shop_';

    private const LOCALE_PREFIX = '/{_locale}';

    /** @test */
    public function it_registers_every_route_routes_yaml_does(): void
    {
        self::assertEqualsCanonicalizing(
            array_keys($this->routes(self::WITH_LOCALE)->all()),
            array_keys($this->routes(self::WITHOUT_LOCALE)->all()),
        );
    }

    /** @test */
    public function it_registers_the_shop_routes_without_a_locale(): void
    {
        $withLocale = $this->routes(self::WITH_LOCALE);
        $shopRoutes = self::shopRoutes($this->routes(self::WITHOUT_LOCALE));
        self::assertNotEmpty($shopRoutes, 'The plugin registers no shop route');

        foreach ($shopRoutes as $name => $route) {
            self::assertStringNotContainsString('{_locale}', $route->getPath(), $name);
            self::assertFalse($route->hasRequirement('_locale'), $name);

            // Apart from the locale it is the route routes.yaml registers
            $expected = $withLocale->get($name);
            self::assertInstanceOf(Route::class, $expected, $name);
            $expected = clone $expected;
            self::assertStringStartsWith(self::LOCALE_PREFIX, $expected->getPath(), $name);
            $expected->setPath(substr($expected->getPath(), strlen(self::LOCALE_PREFIX)));
            $expected->setRequirements(array_diff_key($expected->getRequirements(), ['_locale' => true]));

            self::assertEquals($expected, $route, $name);
        }
    }

    /** @test */
    public function it_registers_the_admin_routes_where_routes_yaml_does(): void
    {
        $withLocale = $this->routes(self::WITH_LOCALE);
        $adminRoutes = self::adminRoutes($this->routes(self::WITHOUT_LOCALE));
        self::assertNotEmpty($adminRoutes, 'The plugin registers no admin route');

        foreach ($adminRoutes as $name => $route) {
            self::assertEquals($withLocale->get($name), $route, $name);
        }
    }

    /**
     * Only the admin path SYLIUS_ADMIN_ROUTING_PATH_NAME names is behind the admin firewall and its access control, so
     * the admin routes go wherever that is rather than under /admin
     *
     * @test
     */
    public function it_puts_the_admin_routes_under_the_admin_path(): void
    {
        $adminRoutes = self::adminRoutes($this->routes(self::WITHOUT_LOCALE));
        self::assertNotEmpty($adminRoutes, 'The plugin registers no admin route');

        foreach ($adminRoutes as $name => $route) {
            self::assertStringStartsWith('/%sylius_admin.path_name%/', $route->getPath(), $name);
        }
    }

    /**
     * @return RouteCollection the routes the file registers as it writes them. The router would replace the admin path
     * parameter in them with the test application's value, /admin, which a prefix hard-coded to /admin matches too
     */
    private function routes(string $resource): RouteCollection
    {
        $loader = self::getContainer()->get('routing.loader');
        self::assertInstanceOf(LoaderInterface::class, $loader);

        $routes = $loader->load($resource);
        self::assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }

    /**
     * @return array<string, Route>
     */
    private static function shopRoutes(RouteCollection $routes): array
    {
        return array_filter(
            $routes->all(),
            static fn (string $name): bool => str_starts_with($name, self::SHOP_ROUTE_PREFIX),
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<string, Route>
     */
    private static function adminRoutes(RouteCollection $routes): array
    {
        return array_diff_key($routes->all(), self::shopRoutes($routes));
    }
}
