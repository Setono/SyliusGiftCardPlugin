<?php

declare(strict_types=1);

use Setono\SyliusGiftCardPlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__) . '/Application/config/bootstrap.php';

$kernel = new Kernel('test', true);

return new Application($kernel);
