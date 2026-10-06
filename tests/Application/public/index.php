<?php

declare(strict_types=1);

use Setono\SyliusGiftCardPlugin\Tests\Application\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

// Composer's autoloader runs before Debug::enable() below, and with it api-platform 2.7's deprecation.php, which loads
// interfaces PHP 8.4 reports as deprecated while compiling them (implicitly nullable parameters). Symfony's error
// handler is not registered yet, and under OPcache it would not see those notices anyway (php-src GH-17422), so with a
// development php.ini PHP printed them into the response, which then could not start its session and answered 500.
// Deprecations are therefore left out of PHP's own error reporting from the start, as Debug::enable() leaves them out
// since symfony/error-handler 6.4.23. Symfony's error handler logs every deprecation it receives whatever the level
error_reporting(error_reporting() & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

require dirname(__DIR__) . '/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
