<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\AddAdjustmentsToOrderAdjustmentClearerPass;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\DefineControllerPass;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\OverrideCouponFormPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusGiftCardPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddAdjustmentsToOrderAdjustmentClearerPass());
        $container->addCompilerPass(new OverrideCouponFormPass());
        $container->addCompilerPass(new DefineControllerPass());
    }

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }
}
