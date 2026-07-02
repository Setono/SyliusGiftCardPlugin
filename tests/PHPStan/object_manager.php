<?php

declare(strict_types=1);

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusGiftCardPlugin\Tests\Application\Kernel;

require dirname(__DIR__) . '/Application/config/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

/** @var ManagerRegistry $registry */
$registry = $kernel->getContainer()->get('doctrine');

return $registry->getManager();
