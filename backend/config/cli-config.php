<?php
/**
 * Configuration for vendor/bin/doctrine-migrations (no longer for vendor/bin/doctrine)
 */

declare(strict_types=1);

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\YamlFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Neucore\Application;

require __DIR__ . '/../vendor/autoload.php';

/** @noinspection PhpUnhandledExceptionInspection */
$em = (new Application())->buildContainer()->get(EntityManagerInterface::class);
//$em->getConnection()->setAutoCommit(false);

return DependencyFactory::fromEntityManager(
    new YamlFile('config/migrations.yml'),
    new ExistingEntityManager($em)
);
