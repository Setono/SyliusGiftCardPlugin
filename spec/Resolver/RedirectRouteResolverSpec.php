<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Resolver;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectRouteResolver;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectRouteResolverInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RedirectRouteResolverSpec extends ObjectBehavior
{
    public function let(UrlGeneratorInterface $router): void
    {
        $this->beConstructedWith($router);
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(RedirectRouteResolver::class);
    }

    public function it_implements_redirect_route_resolver_interface(): void
    {
        $this->shouldImplement(RedirectRouteResolverInterface::class);
    }

    public function it_returns_redirect_from_route_attributes(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag(['redirect' => ['sylius_shop_homepage']]);
        $request->attributes = $attributesBag;

        $router->generate('sylius_shop_homepage')->willReturn('super-url');

        $this->getRouteToRedirectTo($request, 'random')->shouldReturn('super-url');
    }

    public function it_returns_redirect_from_route_attributes_with_parameters(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag([
            'redirect' => [
                'route' => 'sylius_shop_homepage',
                'parameters' => ['a' => 1],
            ],
        ]);
        $request->attributes = $attributesBag;

        $router->generate('sylius_shop_homepage', ['a' => 1])->willReturn('super-url-1');

        $this->getRouteToRedirectTo($request, 'random')->shouldReturn('super-url-1');
    }

    public function it_returns_redirect_from_route_referer(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag([]);
        $request->attributes = $attributesBag;

        $headersBag = new HeaderBag(['referer' => 'some-url']);
        $request->headers = $headersBag;

        $this->getRouteToRedirectTo($request, 'random')->shouldReturn('some-url');
    }

    public function it_returns_fallback(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag([]);
        $request->attributes = $attributesBag;

        $headersBag = new HeaderBag([]);
        $request->headers = $headersBag;

        $router->generate('fallback')->willReturn('fallback-url');

        $this->getRouteToRedirectTo($request, 'fallback')->shouldReturn('fallback-url');
    }
}
