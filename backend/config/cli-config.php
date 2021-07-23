<?php
/**
 * Required configuration for vendor/bin/doctrine
 */

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Neucore\Application;

require __DIR__ . '/../vendor/autoload.php';

$em = (new Application())->buildContainer()->get(EntityManagerInterface::class);
$em->getConnection()->setAutoCommit(false);

return ConsoleRunner::createHelperSet($em);
