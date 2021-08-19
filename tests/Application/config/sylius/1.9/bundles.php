<?php

return [
    FOS\OAuthServerBundle\FOSOAuthServerBundle::class => ['all' => true],
    Sylius\Bundle\AdminApiBundle\SyliusAdminApiBundle::class => ['all' => true],
    BabDev\PagerfantaBundle\BabDevPagerfantaBundle::class => ['all' => true],
    SyliusLabs\Polyfill\Symfony\Security\Bundle\SyliusLabsPolyfillSymfonySecurityBundle::class => ['all' => true],
    SyliusLabs\DoctrineMigrationsExtraBundle\SyliusLabsDoctrineMigrationsExtraBundle::class => ['all' => true],
    Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
    ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    Sylius\Bundle\ApiBundle\SyliusApiBundle::class => ['all' => true],
];
