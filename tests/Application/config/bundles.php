<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Sylius\Bundle\OrderBundle\SyliusOrderBundle;
use Sylius\Bundle\MoneyBundle\SyliusMoneyBundle;
use Sylius\Bundle\CurrencyBundle\SyliusCurrencyBundle;
use Sylius\Bundle\LocaleBundle\SyliusLocaleBundle;
use Sylius\Bundle\ProductBundle\SyliusProductBundle;
use Sylius\Bundle\ChannelBundle\SyliusChannelBundle;
use Sylius\Bundle\AttributeBundle\SyliusAttributeBundle;
use Sylius\Bundle\TaxationBundle\SyliusTaxationBundle;
use Sylius\Bundle\ShippingBundle\SyliusShippingBundle;
use Sylius\Bundle\PaymentBundle\SyliusPaymentBundle;
use Sylius\Bundle\MailerBundle\SyliusMailerBundle;
use Sylius\Bundle\PromotionBundle\SyliusPromotionBundle;
use Sylius\Bundle\AddressingBundle\SyliusAddressingBundle;
use Sylius\Bundle\InventoryBundle\SyliusInventoryBundle;
use Sylius\Bundle\TaxonomyBundle\SyliusTaxonomyBundle;
use Sylius\Bundle\UserBundle\SyliusUserBundle;
use Sylius\Bundle\CustomerBundle\SyliusCustomerBundle;
use Sylius\Bundle\UiBundle\SyliusUiBundle;
use Sylius\Bundle\ReviewBundle\SyliusReviewBundle;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle;
use ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle;
use Sylius\Bundle\ApiBundle\SyliusApiBundle;
use winzou\Bundle\StateMachineBundle\winzouStateMachineBundle;
use Sonata\BlockBundle\SonataBlockBundle;
use Bazinga\Bundle\HateoasBundle\BazingaHateoasBundle;
use JMS\SerializerBundle\JMSSerializerBundle;
use FOS\RestBundle\FOSRestBundle;
use Knp\Bundle\GaufretteBundle\KnpGaufretteBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use League\FlysystemBundle\FlysystemBundle;
use Liip\ImagineBundle\LiipImagineBundle;
use Payum\Bundle\PayumBundle\PayumBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Sylius\Bundle\FixturesBundle\SyliusFixturesBundle;
use Sylius\Bundle\PayumBundle\SyliusPayumBundle;
use Sylius\Bundle\ThemeBundle\SyliusThemeBundle;
use Sylius\Bundle\AdminBundle\SyliusAdminBundle;
use Sylius\Bundle\ShopBundle\SyliusShopBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use FriendsOfBehat\SymfonyExtension\Bundle\FriendsOfBehatSymfonyExtensionBundle;
use Knp\Bundle\SnappyBundle\KnpSnappyBundle;
use SyliusLabs\DoctrineMigrationsExtraBundle\SyliusLabsDoctrineMigrationsExtraBundle;
use BabDev\PagerfantaBundle\BabDevPagerfantaBundle;
use SyliusLabs\Polyfill\Symfony\Security\Bundle\SyliusLabsPolyfillSymfonySecurityBundle;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use Sylius\Calendar\SyliusCalendarBundle;
use Setono\SyliusGiftCardPlugin\SetonoSyliusGiftCardPlugin;
use Sylius\Bundle\GridBundle\SyliusGridBundle;

return [
    FrameworkBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    SyliusOrderBundle::class => ['all' => true],
    SyliusMoneyBundle::class => ['all' => true],
    SyliusCurrencyBundle::class => ['all' => true],
    SyliusLocaleBundle::class => ['all' => true],
    SyliusProductBundle::class => ['all' => true],
    SyliusChannelBundle::class => ['all' => true],
    SyliusAttributeBundle::class => ['all' => true],
    SyliusTaxationBundle::class => ['all' => true],
    SyliusShippingBundle::class => ['all' => true],
    SyliusPaymentBundle::class => ['all' => true],
    SyliusMailerBundle::class => ['all' => true],
    SyliusPromotionBundle::class => ['all' => true],
    SyliusAddressingBundle::class => ['all' => true],
    SyliusInventoryBundle::class => ['all' => true],
    SyliusTaxonomyBundle::class => ['all' => true],
    SyliusUserBundle::class => ['all' => true],
    SyliusCustomerBundle::class => ['all' => true],
    SyliusUiBundle::class => ['all' => true],
    SyliusReviewBundle::class => ['all' => true],
    SyliusCoreBundle::class => ['all' => true],
    SyliusResourceBundle::class => ['all' => true],

    // Begin: In test app, those 3 needs to be loaded before our plugin or the Api Resource override won't work
    LexikJWTAuthenticationBundle::class => ['all' => true],
    ApiPlatformBundle::class => ['all' => true],
    SyliusApiBundle::class => ['all' => true],
    // End: In test app, those 3 needs to be loaded before our plugin or the Api Resource override won't work

    winzouStateMachineBundle::class => ['all' => true],
    SonataBlockBundle::class => ['all' => true],
    BazingaHateoasBundle::class => ['all' => true],
    JMSSerializerBundle::class => ['all' => true],
    FOSRestBundle::class => ['all' => true],
    KnpGaufretteBundle::class => ['all' => true],
    KnpMenuBundle::class => ['all' => true],
    FlysystemBundle::class => ['all' => true],
    LiipImagineBundle::class => ['all' => true],
    PayumBundle::class => ['all' => true],
    StofDoctrineExtensionsBundle::class => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    SyliusFixturesBundle::class => ['all' => true],
    SyliusPayumBundle::class => ['all' => true],
    SyliusThemeBundle::class => ['all' => true],
    SyliusAdminBundle::class => ['all' => true],
    SyliusShopBundle::class => ['all' => true],
    DebugBundle::class => ['dev' => true, 'test' => true],
    WebProfilerBundle::class => ['dev' => true, 'test' => true],
    FriendsOfBehatSymfonyExtensionBundle::class => ['test' => true],
    KnpSnappyBundle::class => ['all' => true],
    SyliusLabsDoctrineMigrationsExtraBundle::class => ['all' => true],
    BabDevPagerfantaBundle::class => ['all' => true],
    SyliusLabsPolyfillSymfonySecurityBundle::class => ['all' => true],
    WebpackEncoreBundle::class => ['all' => true],
    SyliusCalendarBundle::class => ['all' => true],
    SetonoSyliusGiftCardPlugin::class => ['all' => true],
    SyliusGridBundle::class => ['all' => true],
];
