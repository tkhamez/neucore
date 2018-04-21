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

$settings = (new Application())->loadSettings();
$conf = $settings['config']['doctrine'];

$config = Setup::createAnnotationMetadataConfiguration(
    $conf['meta']['entity_path'],
    $conf['meta']['dev_mode'],
    $conf['meta']['proxy_dir']
);

$em = EntityManager::create($conf['connection'], $config);

/* @var $helpers HelperSet */
$helpers = new HelperSet(array(
    'db' => new ConnectionHelper($em->getConnection()),
    'em' => new EntityManagerHelper($em)
));
