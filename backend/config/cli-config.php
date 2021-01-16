<?php

declare(strict_types=1);

/**
 * Required configuration for vendor/bin/doctrine
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Tools\Setup;
use Neucore\Application;
use Symfony\Component\Console\Helper\HelperSet;

require __DIR__ . '/../vendor/autoload.php';

$conf = (new Application())->loadSettings()['doctrine'];

$config = Setup::createAnnotationMetadataConfiguration(
    $conf['meta']['entity_paths'],
    $conf['meta']['dev_mode'],
    $conf['meta']['proxy_dir'],
    null,
    false
);
AnnotationRegistry::registerLoader('class_exists');

$em = EntityManager::create($conf['connection'], $config);
$em->getConnection()->setAutoCommit(false);

return new HelperSet(array(
    'db' => new ConnectionHelper($em->getConnection()), # TODO what's the replacement?
    'em' => new EntityManagerHelper($em)
));
