<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin;

use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\AddAdjustmentsToOrderAdjustmentClearerPass;
use Setono\SyliusGiftCardPlugin\DependencyInjection\Compiler\CreateServiceAliasesPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusGiftCardPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddAdjustmentsToOrderAdjustmentClearerPass());
        $container->addCompilerPass(new CreateServiceAliasesPass());
    }

    #[\Override]
    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    #[\Override]
    protected function getConfigFilesPath(): string
    {
        return sprintf('%s/config/doctrine/%s', $this->getPath(), strtolower($this->getDoctrineMappingDirectory()));
    }
}
