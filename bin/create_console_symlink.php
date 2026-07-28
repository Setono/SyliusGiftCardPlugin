<?php

declare(strict_types=1);

$binDir = __DIR__;
$projectRoot = dirname($binDir);

$linkPath = $binDir . DIRECTORY_SEPARATOR . 'console';

$targetRelativeFromBin = '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';

function fail(string $message, int $code = 1): void {
    fwrite(STDERR, "[create-console-symlink] {$message}\n");
    exit($code);
}

function info(string $message): void {
    fwrite(STDOUT, "[create-console-symlink] {$message}\n");
}

$targetAbsolute = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';

if (is_link($linkPath) || file_exists($linkPath)) {
    if (!@unlink($linkPath)) {
        fail("Unable to remove existing bin/console (check permissions).");
    }
    info("Removed existing: bin/console");
}

$isWindows = (PHP_OS_FAMILY === 'Windows');

if ($isWindows) {
    $batPath = $linkPath . '.bat';
    $bat = "@echo off\r\n"
        . "php \"%~dp0..\\vendor\\bin\\console\" %*\r\n";

    if (file_put_contents($batPath, $bat) === false) {
        fail("Unable to write: bin/console.bat");
    }
    info("Created launcher: bin/console.bat");
    exit(0);
}

if (!file_exists($targetAbsolute)) {
    info("Warning: target not found yet: vendor/bin/console (will still create symlink).");
}

if (!symlink($targetRelativeFromBin, $linkPath)) {
    fail("Unable to create symlink bin/console -> {$targetRelativeFromBin}");
}

@chmod($linkPath, 0755);
info("Created symlink: bin/console -> {$targetRelativeFromBin}");
