<?php

require './vendor/autoload.php';

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Symfony\Component\Console\Input\ArrayInput;

if (!\class_exists(ContaoKernel::class)) {
    return;
}

$kernel = ContaoKernel::fromInput(getcwd(), new ArrayInput([]));
$kernel->boot();

return $kernel->getContainer();
