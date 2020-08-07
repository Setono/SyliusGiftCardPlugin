<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Resolver;

use Symfony\Component\HttpFoundation\Request;

interface RedirectRouteResolverInterface
{
    public function getRouteToRedirectTo(Request $request, string $defaultRoute): string;
}
