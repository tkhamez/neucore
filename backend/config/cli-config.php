<?php declare(strict_types=1);

/**
 * Required configuration for vendor/bin/doctrine
 */

use Brave\Core\Application;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Console\Helper\HelperSet;

require __DIR__ . '/../vendor/autoload.php';

$settings = (new Application())->loadSettings();

$conf = $settings['config']['doctrine'];

$config = Setup::createAnnotationMetadataConfiguration(
    $conf['meta']['entity_paths'],
    $conf['meta']['dev_mode'],
    $conf['meta']['proxy_dir'],
    null,
    false
);
AnnotationRegistry::registerLoader('class_exists');

$em = EntityManager::create($conf['connection'], $config);

return new HelperSet(array(
    'db' => new ConnectionHelper($em->getConnection()),
    'em' => new EntityManagerHelper($em)
));
