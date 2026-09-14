<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends the customer back to the page the apply/remove form was submitted from, i.e. the Referer.
 *
 * The Referer is supplied by the client, so it is only followed when it stays on this host: a relative path,
 * or an absolute URL on the request's own scheme, host and port. Anything else (another host, a protocol
 * relative "//evil" form, a "javascript:" URL) falls back to the default route
 */
final class RedirectUrlResolver implements RedirectUrlResolverInterface
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    public function getUrlToRedirectTo(Request $request, string $defaultRoute): string
    {
        $referer = $request->headers->get('referer');
        if (is_string($referer) && self::isOnThisHost($referer, $request)) {
            return $referer;
        }

        return $this->router->generate($defaultRoute);
    }

    private static function isOnThisHost(string $url, Request $request): bool
    {
        if (str_starts_with($url, '/')) {
            // "//evil" is protocol relative, and browsers treat "/\evil" the same way
            return !str_starts_with($url, '//') && !str_starts_with($url, '/\\');
        }

        $origin = $request->getSchemeAndHttpHost();
        if (!str_starts_with($url, $origin)) {
            return false;
        }

        // "https://shop.example.com.evil.com" and "https://shop.example.com@evil.com" also start with the origin,
        // so the origin has to be followed by the end of the URL or by a path, query or fragment
        $next = substr($url, strlen($origin), 1);

        return '' === $next || '/' === $next || '?' === $next || '#' === $next;
    }
}
