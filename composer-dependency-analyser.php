<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // The dependency analysis job unsets require-dev before installing, so that the Sylius
    // components this plugin requires resolve to the split sylius/* packages instead of being
    // masked by the sylius/sylius monolith. That leaves the test application's own dependencies
    // uninstalled, so nothing under tests/ can be analysed meaningfully.
    ->addPathToExclude(__DIR__ . '/tests')

    // Required so host applications get SyliusGridBundle, whose grids this plugin prepends
    // configuration for in SetonoSyliusGiftCardExtension. There is no class reference to it.
    ->ignoreErrorsOnPackage('sylius/grid-bundle', [ErrorType::UNUSED_DEPENDENCY])

    // Provides the translator that loads src/Resources/translations/*.yml and backs the
    // `trans` filter used in this plugin's templates. Again, no class reference.
    ->ignoreErrorsOnPackage('symfony/translation', [ErrorType::UNUSED_DEPENDENCY])
;
