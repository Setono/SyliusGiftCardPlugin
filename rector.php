<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./.build/rector');

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/Application',
        // the Playwright suite is JavaScript
        __DIR__ . '/tests/Playwright',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
    ]);
};
