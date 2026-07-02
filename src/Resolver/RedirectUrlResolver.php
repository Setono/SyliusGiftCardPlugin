<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use const FILTER_SANITIZE_URL;
use function filter_var;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RedirectUrlResolver implements RedirectUrlResolverInterface
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    public function getUrlToRedirectTo(Request $request, string $defaultRoute): string
    {
        /** @var mixed $redirect */
        $redirect = $request->attributes->get('redirect');
        if (is_array($redirect)) {
            if (isset($redirect[0]) && is_string($redirect[0])) {
                return $this->router->generate($redirect[0]);
            }

            if (isset($redirect['route'], $redirect['parameters']) && is_string($redirect['route']) && is_array($redirect['parameters'])) {
                return $this->router->generate($redirect['route'], $redirect['parameters']);
            }
        }

        $referrer = $request->headers->get('referer');
        if (is_string($referrer)) {
            /** @var mixed $redirectTo */
            $redirectTo = filter_var($referrer, FILTER_SANITIZE_URL);

            if (is_string($redirectTo)) {
                return $redirectTo;
            }
        }

        return $this->router->generate($defaultRoute);
    }
}
