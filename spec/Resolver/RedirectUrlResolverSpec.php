<?php

declare(strict_types=1);

namespace spec\Setono\SyliusGiftCardPlugin\Resolver;

use PhpSpec\ObjectBehavior;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolver;
use Setono\SyliusGiftCardPlugin\Resolver\RedirectUrlResolverInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RedirectUrlResolverSpec extends ObjectBehavior
{
    public function let(UrlGeneratorInterface $router): void
    {
        $this->beConstructedWith($router);
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(RedirectUrlResolver::class);
    }

    public function it_implements_redirect_route_resolver_interface(): void
    {
        $this->shouldImplement(RedirectUrlResolverInterface::class);
    }

    public function it_returns_redirect_from_route_attributes(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag(['redirect' => ['sylius_shop_homepage']]);
        $request->attributes = $attributesBag;

        $router->generate('sylius_shop_homepage')->willReturn('super-url');

        $this->getUrlToRedirectTo($request, 'random')->shouldReturn('super-url');
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

        $this->getUrlToRedirectTo($request, 'random')->shouldReturn('super-url-1');
    }

    public function it_returns_redirect_from_route_referer(
        UrlGeneratorInterface $router,
        Request $request
    ): void {
        $attributesBag = new ParameterBag([]);
        $request->attributes = $attributesBag;

        $headersBag = new HeaderBag(['referer' => 'some-url']);
        $request->headers = $headersBag;

        $this->getUrlToRedirectTo($request, 'random')->shouldReturn('some-url');
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

        $this->getUrlToRedirectTo($request, 'fallback')->shouldReturn('fallback-url');
    }
}
