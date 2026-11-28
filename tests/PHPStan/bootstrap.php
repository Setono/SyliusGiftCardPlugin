<?php

declare(strict_types=1);

// Composer's autoloader, and the alias Sylius' resource bundle registers for its deprecated
// Sylius\Component\Resource\Factory\FactoryInterface. The bundle only registers it as a side effect of loading
// Sylius\Resource\Factory\FactoryInterface, so whether PHPStan read the deprecated name as that generic interface or as
// the non-generic declaration in the bundle's legacy file depended on what a worker happened to load first, and a
// FactoryInterface<...> annotation failed in whichever file a run's split of the files put before it
require dirname(__DIR__, 2) . '/vendor/autoload.php';

interface_exists(\Sylius\Resource\Factory\FactoryInterface::class);
