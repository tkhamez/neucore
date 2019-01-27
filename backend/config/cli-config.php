<?php declare(strict_types=1);

/**
 * Required configuration for vendor/bin/doctrine
 */

use Brave\Core\Application;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Console\Helper\HelperSet;

require __DIR__ . '/../vendor/autoload.php';

try {
    $settings = (new Application())->loadSettings();
} catch(\Exception $e) {
    // The .env file could not be loaded, but some commands (orm:generate-proxies specifically)
    // don't need a connection, so this works:
    $settings = include 'settings.php';
    $settings['config']['doctrine']['connection']['url'] = 'mysql://core:brave@localhost/core';
}

$conf = $settings['config']['doctrine'];

$config = Setup::createAnnotationMetadataConfiguration(
    $conf['meta']['entity_paths'],
    $conf['meta']['dev_mode'],
    $conf['meta']['proxy_dir']
);

$em = EntityManager::create($conf['connection'], $config);

/* @var $helpers HelperSet */
$helpers = new HelperSet(array(
    'db' => new ConnectionHelper($em->getConnection()),
    'em' => new EntityManagerHelper($em)
));
