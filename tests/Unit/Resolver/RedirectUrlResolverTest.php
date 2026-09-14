<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The Referer decides where the customer lands after applying or removing a gift card. It is sent by the
 * client, so only a referer that stays on the shop's own host may be followed; anything else must fall back
 * to the default route instead of becoming an open redirect
 */
final class RedirectUrlResolverTest extends TestCase
{
    use ProphecyTrait;

    private const DEFAULT_ROUTE = 'sylius_shop_cart_summary';

    private const DEFAULT_URL = '/en_US/cart/';

    /**
     * @test
     *
     * @dataProvider onSiteReferers
     */
    public function it_follows_a_referer_that_stays_on_this_host(string $origin, string $referer): void
    {
        $router = $this->prophesize(UrlGeneratorInterface::class);
        $router->generate(Argument::cetera())->shouldNotBeCalled();

        $resolver = new RedirectUrlResolver($router->reveal());

        self::assertSame($referer, $resolver->getUrlToRedirectTo($this->request($origin, $referer), self::DEFAULT_ROUTE));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function onSiteReferers(): iterable
    {
        yield 'a page on this host' => ['https://shop.example.com', 'https://shop.example.com/en_US/cart/'];
        yield 'a page with a query and a fragment' => ['https://shop.example.com', 'https://shop.example.com/en_US/cart/?page=2#gift-cards'];
        yield 'the bare origin' => ['https://shop.example.com', 'https://shop.example.com'];
        yield 'this host on a non standard port' => ['http://127.0.0.1:8080', 'http://127.0.0.1:8080/en_US/cart/'];
        yield 'a relative path' => ['https://shop.example.com', '/en_US/cart/'];
    }

    /**
     * @test
     *
     * @dataProvider offSiteReferers
     */
    public function it_ignores_a_referer_that_leaves_this_host(string $referer): void
    {
        $resolver = new RedirectUrlResolver($this->routerGeneratingTheDefaultRoute());

        self::assertSame(self::DEFAULT_URL, $resolver->getUrlToRedirectTo($this->request('https://shop.example.com', $referer), self::DEFAULT_ROUTE));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function offSiteReferers(): iterable
    {
        yield 'another host' => ['https://evil.example.com/en_US/cart/'];
        yield 'a host that merely starts with ours' => ['https://shop.example.com.evil.example.com/en_US/cart/'];
        yield 'our host as the user info of another host' => ['https://shop.example.com@evil.example.com/en_US/cart/'];
        yield 'our host on another port' => ['https://shop.example.com:8443/en_US/cart/'];
        yield 'our host on another scheme' => ['http://shop.example.com/en_US/cart/'];
        yield 'a protocol relative URL' => ['//evil.example.com/en_US/cart/'];
        yield 'a protocol relative URL spelled with a backslash' => ['/\\evil.example.com/en_US/cart/'];
        yield 'a javascript URL' => ['javascript:alert(1)'];
    }

    /**
     * @test
     *
     * @dataProvider missingReferers
     */
    public function it_falls_back_to_the_default_route_without_a_referer(?string $referer): void
    {
        $resolver = new RedirectUrlResolver($this->routerGeneratingTheDefaultRoute());

        self::assertSame(self::DEFAULT_URL, $resolver->getUrlToRedirectTo($this->request('https://shop.example.com', $referer), self::DEFAULT_ROUTE));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function missingReferers(): iterable
    {
        yield 'no referer header' => [null];
        yield 'an empty referer header' => [''];
    }

    /**
     * A host can pin the destination through a "redirect" route default; that wins over the Referer
     *
     * @test
     *
     * @dataProvider redirectAttributes
     *
     * @param array<array-key, mixed> $redirect
     * @param array<string, mixed> $expectedParameters
     */
    public function it_prefers_a_redirect_configured_on_the_route(array $redirect, string $expectedRoute, array $expectedParameters): void
    {
        $router = $this->prophesize(UrlGeneratorInterface::class);
        if ([] === $expectedParameters) {
            $router->generate($expectedRoute)->willReturn('/en_US/thank-you');
        } else {
            $router->generate($expectedRoute, $expectedParameters)->willReturn('/en_US/thank-you');
        }

        $request = $this->request('https://shop.example.com', 'https://shop.example.com/en_US/cart/');
        $request->attributes->set('redirect', $redirect);

        $resolver = new RedirectUrlResolver($router->reveal());

        self::assertSame('/en_US/thank-you', $resolver->getUrlToRedirectTo($request, self::DEFAULT_ROUTE));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, array<string, mixed>}>
     */
    public static function redirectAttributes(): iterable
    {
        yield 'a route name' => [['sylius_shop_order_thank_you'], 'sylius_shop_order_thank_you', []];
        yield 'a route with parameters' => [['route' => 'sylius_shop_product_show', 'parameters' => ['slug' => 'gift-card']], 'sylius_shop_product_show', ['slug' => 'gift-card']];
    }

    /**
     * @test
     */
    public function it_ignores_a_malformed_redirect_attribute_and_follows_the_referer(): void
    {
        $router = $this->prophesize(UrlGeneratorInterface::class);
        $router->generate(Argument::cetera())->shouldNotBeCalled();

        $request = $this->request('https://shop.example.com', 'https://shop.example.com/en_US/cart/');
        $request->attributes->set('redirect', ['parameters' => ['slug' => 'gift-card']]);

        $resolver = new RedirectUrlResolver($router->reveal());

        self::assertSame('https://shop.example.com/en_US/cart/', $resolver->getUrlToRedirectTo($request, self::DEFAULT_ROUTE));
    }

    private function routerGeneratingTheDefaultRoute(): UrlGeneratorInterface
    {
        $router = $this->prophesize(UrlGeneratorInterface::class);
        $router->generate(self::DEFAULT_ROUTE)->willReturn(self::DEFAULT_URL);

        return $router->reveal();
    }

    /**
     * A POST to the apply action on the given origin, optionally carrying a Referer header
     */
    private function request(string $origin, ?string $referer): Request
    {
        $server = [];
        if (null !== $referer) {
            $server['HTTP_REFERER'] = $referer;
        }

        return Request::create($origin . '/en_US/gift-cards', 'POST', [], [], [], $server);
    }
}
