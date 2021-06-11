<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import('vendor/sylius-labs/coding-standard/ecs.php');
    $containerConfigurator->parameters()->set(Option::PATHS, [
        'src', 'spec', 'tests'
    ]);
    $containerConfigurator->parameters()->set(Option::SKIP, [
        'tests/Application/**',
    ]);
};
