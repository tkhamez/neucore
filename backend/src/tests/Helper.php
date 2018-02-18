<?php

namespace Tests;

use Brave\Core\Application;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\User;
use Brave\Core\Entity\App;

class Helper
{

    /**
     * @var EntityManager
     */
    private static $em;

    private $entities = [
        'Brave\Core\Entity\App',
        'Brave\Core\Entity\Group',
        'Brave\Core\Entity\Role',
        'Brave\Core\Entity\User',
    ];

    public function getEm()
    {
        if (self::$em === null) {
            $settings = (new Application())->settings(true);

            $config = Setup::createAnnotationMetadataConfiguration(
                $settings['config']['doctrine']['meta']['entity_path'],
                $settings['config']['doctrine']['meta']['dev_mode'],
                $settings['config']['doctrine']['meta']['proxy_dir']
            );

            self::$em = EntityManager::create($settings['config']['doctrine']['connection'], $config);
        }

        return self::$em;
    }

    public function updateDbSchema()
    {
        $em = self::getEm();

        $classes = [];
        foreach ($this->entities as $entity) {
            $classes[] = $em->getClassMetadata($entity);
        }

        $tool = new SchemaTool($em);
        $tool->updateSchema($classes);
    }

    public function emptyDb()
    {
        $em = self::getEm();
        $connection = $em->getConnection();

        foreach ($this->entities as $entity) {
            $class = $em->getClassMetadata($entity);
            $connection->query('DELETE FROM ' . $class->getTableName());
        }
    }

    public function addStandardUser()
    {
        $em = $this->getEm();

        $role = new Role();
        $role->setName('user');
        $em->persist($role);

        $user = new User();
        $user->setCharacterId(123456);
        $user->setName('Test User');
        $user->addRole($role);
        $em->persist($user);

        $em->flush();

        return $user->getId();
    }

    public function addStandardApp()
    {
        $em = $this->getEm();

        $role = new Role();
        $role->setName('app');
        $em->persist($role);

        $app = new App();
        $app->setName('Test App');
        $app->setSecret(password_hash('boring-test-secret', PASSWORD_DEFAULT));
        $app->addRole($role);
        $em->persist($app);

        $em->flush();

        return $app->getId();
    }
}
