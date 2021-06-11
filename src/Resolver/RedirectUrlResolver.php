<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use function array_key_exists;
use const FILTER_SANITIZE_URL;
use function filter_var;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RedirectUrlResolver implements RedirectUrlResolverInterface
{
    private UrlGeneratorInterface $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function getUrlToRedirectTo(Request $request, string $defaultRoute): string
    {
        if ($request->attributes->has('redirect')) {
            $redirect = $request->attributes->get('redirect');

            if (array_key_exists(0, $redirect)) {
                return $this->router->generate($redirect[0]);
            }
            if (array_key_exists('route', $redirect) && array_key_exists('parameters', $redirect)) {
                return $this->router->generate($redirect['route'], $redirect['parameters']);
            }
        }

        if ($request->headers->has('referer')) {
            $redirectTo = filter_var($request->headers->get('referer'), FILTER_SANITIZE_URL);

            if (false !== $redirectTo) {
                return $redirectTo;
            }
        }

        return $this->router->generate($defaultRoute);
    }
}
