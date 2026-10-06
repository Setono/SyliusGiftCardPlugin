<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Tests\Application\Session;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * The session storage of the test environment, which serves two kinds of clients.
 *
 * The functional tests send their requests through the kernel from the command line, where PHP's native session cannot
 * start once PHPUnit has written output, so they get Symfony's mock file storage, as before. The Playwright suite sends
 * a real browser to the application served by php-fpm, and gets PHP's native session: the mock storage has no locking,
 * so two requests of one session that overlap (a page and the AJAX call it fires) each write the whole session back,
 * and the later one silently undoes what the earlier one stored, such as a flash message or a CSRF token
 */
final class ServedOrMockSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private readonly SessionStorageFactoryInterface $native,
        private readonly SessionStorageFactoryInterface $mock,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        return 'cli' === \PHP_SAPI ? $this->mock->createStorage($request) : $this->native->createStorage($request);
    }
}
