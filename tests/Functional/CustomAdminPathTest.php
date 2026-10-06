<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Functional;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Router;

/**
 * Sylius puts its admin under the path SYLIUS_ADMIN_ROUTING_PATH_NAME names, and only that path is behind the admin
 * firewall and its access control. The plugin's admin pages have to move there with the rest of the admin: left under
 * /admin in a shop whose admin lives elsewhere, the shop firewall answered them without a login, handing anyone the PDF
 * of a card with its code and a search through the customers' emails.
 *
 * The container reads the admin path from the environment when it is asked for it, so the test kernel serves any admin
 * path without being compiled again
 */
final class CustomAdminPathTest extends AdminFunctionalTestCase
{
    private const ENV_VAR = 'SYLIUS_ADMIN_ROUTING_PATH_NAME';

    private const ADMIN_PATH = 'backoffice';

    /** @var array{server: mixed, env: mixed} what the two superglobals held before */
    private array $previous = ['server' => null, 'env' => null];

    protected function setUp(): void
    {
        $this->previous = ['server' => $_SERVER[self::ENV_VAR] ?? null, 'env' => $_ENV[self::ENV_VAR] ?? null];
        $_SERVER[self::ENV_VAR] = $_ENV[self::ENV_VAR] = self::ADMIN_PATH;

        parent::setUp();

        // The routes the router compiles into the cache directory are the default admin path's. Outside debug mode
        // they would answer here as they are, and in debug mode they would be compiled over for this test and back
        // again for the next one, so this admin path gets a directory of its own
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $this->router()->setOption('cache_dir', $kernel->getCacheDir() . '/admin_path_' . self::ADMIN_PATH);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER[self::ENV_VAR], $_ENV[self::ENV_VAR]);
        if (null !== $this->previous['server']) {
            $_SERVER[self::ENV_VAR] = $this->previous['server'];
        }
        if (null !== $this->previous['env']) {
            $_ENV[self::ENV_VAR] = $this->previous['env'];
        }
    }

    /** @test */
    public function it_puts_every_admin_route_of_the_plugin_under_the_admin_path(): void
    {
        $paths = [];
        foreach ($this->router()->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, 'setono_sylius_gift_card_admin')) {
                $paths[$name] = $route->getPath();
            }
        }

        self::assertNotEmpty($paths, 'The plugin registers no admin route');
        foreach ($paths as $name => $path) {
            self::assertStringStartsWith('/backoffice/', $path, sprintf('The route %s is outside the admin path', $name));
        }
    }

    /** @test */
    public function it_sends_a_visitor_who_is_not_signed_in_to_the_admin_login(): void
    {
        $giftCard = $this->persistGiftCard('BACKOFFICEPDF01', 5000);

        $pdf = $this->request('GET', sprintf('/backoffice/gift-cards/%d/pdf', (int) $giftCard->getId()));
        self::assertTrue($pdf->isRedirect('http://localhost/backoffice/login'), 'The PDF should be behind the admin login');

        $search = $this->request('GET', '/backoffice/ajax/customer/search', ['phrase' => '@']);
        self::assertTrue($search->isRedirect('http://localhost/backoffice/login'), 'The customer search should be behind the admin login');
    }

    /** @test */
    public function it_leaves_nothing_of_the_plugin_under_the_default_admin_path(): void
    {
        $giftCard = $this->persistGiftCard('BACKOFFICEPDF01', 5000);

        self::assertSame(404, $this->request('GET', sprintf('/admin/gift-cards/%d/pdf', (int) $giftCard->getId()))->getStatusCode());
        self::assertSame(404, $this->request('GET', '/admin/ajax/customer/search', ['phrase' => '@'])->getStatusCode());
    }

    /** @test */
    public function it_serves_a_signed_in_administrator_under_the_admin_path(): void
    {
        $this->logInAsAdministrator();
        $giftCard = $this->persistGiftCard('BACKOFFICEPDF01', 5000);

        $pdf = $this->request('GET', sprintf('/backoffice/gift-cards/%d/pdf', (int) $giftCard->getId()));
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        // The pages link to each other by route name, so every link to a gift card page follows the admin path
        $index = $this->request('GET', '/backoffice/gift-cards/');
        self::assertSame(200, $index->getStatusCode());

        $links = self::textsOf($index, '//a/@href[contains(., "/gift-card")]');
        self::assertNotEmpty($links, 'The gift card index links to no gift card page');
        foreach ($links as $link) {
            self::assertStringStartsWith('/backoffice/', $link);
        }
    }

    private function router(): Router
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(Router::class, $router);

        return $router;
    }
}
