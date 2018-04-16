<?php

namespace Tests;

use Brave\Core\Application;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\RoleRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;

class Helper
{

    /**
     * @var EntityManager
     */
    private static $em;

    private $entities = [
        'Brave\Core\Entity\App',
        'Brave\Core\Entity\Character',
        'Brave\Core\Entity\Player',
        'Brave\Core\Entity\Group',
        'Brave\Core\Entity\Role',
    ];

    public function resetSessionData()
    {
        unset($_SESSION);

        $rp = new \ReflectionProperty('Brave\Slim\Session\SessionData', 'sess');
        $rp->setAccessible(true);
        $rp->setValue(null);

        $rp = new \ReflectionProperty('Brave\Slim\Session\SessionData', 'readOnly');
        $rp->setAccessible(true);
        $rp->setValue(true);
    }

    public function getEm()
    {
        if (self::$em === null) {
            $settings = (new Application())->loadSettings(true);

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
        $em = $this->getEm();

        $classes = [];
        foreach ($this->entities as $entity) {
            $classes[] = $em->getClassMetadata($entity);
        }

        $tool = new SchemaTool($em);
        $tool->updateSchema($classes);
    }

    public function emptyDb()
    {
        $em = $this->getEm();
        $qb = $em->createQueryBuilder();

        foreach ($this->entities as $entity) {
            $qb->delete($entity)->getQuery()->execute();
        }

        $em->clear();
    }

    /**
     *
     * @param array $roles
     * @return \Brave\Core\Entity\Role[]
     */
    public function addRoles($roles)
    {
        $em = $this->getEm();
        $rr = new RoleRepository($em);

        $roleEntities = [];
        foreach ($roles as $roleName) {
            $role = $rr->findOneBy(['name' => $roleName]);
            if ($role === null) {
                $role = new Role();
                $role->setName($roleName);
                $em->persist($role);
            }
            $roleEntities[] = $role;
        }
        $em->flush();

        return $roleEntities;
    }

    /**
     *
     * @param array $groups
     * @return \Brave\Core\Entity\Group[]
     */
    public function addGroups($groups)
    {
        $em = $this->getEm();
        $gr = new GroupRepository($em);

        $groupEntities = [];
        foreach ($groups as $groupName) {
            $group = $gr->findOneBy(['name' => $groupName]);
            if ($group === null) {
                $group = new Group();
                $group->setName($groupName);
                $em->persist($group);
            }
            $groupEntities[] = $group;
        }
        $em->flush();

        return $groupEntities;
    }

    /**
     *
     * @param string $name
     * @param int $charId
     * @param array $roles
     * @return Character
     */
    public function addCharacterMain(string $name, int $charId, array $roles, array $groups = [])
    {
        $em = $this->getEm();

        $player = new Player();
        $player->setName($name);

        $char = new Character();
        $char->setId($charId);
        $char->setName($name);
        $char->setMain(true);
        $char->setCharacterOwnerHash('123');
        $char->setAccessToken('abc');
        $char->setExpires(123456);
        $char->setRefreshToken('def');

        $char->setPlayer($player);
        $player->addCharacter($char);

        foreach ($this->addRoles($roles) as $role) {
            $player->addRole($role);
        }

        foreach ($this->addGroups($groups) as $group) {
            $player->addGroup($group);
        }

        $em->persist($player);
        $em->persist($char);
        $em->flush();

        return $char;
    }

    /**
     *
     * @param string $name
     * @param string $secret
     * @param array $roles
     * @param mixed $hash PASSWORD_DEFAULT or 'md5' (this is only to test password_needs_rehash())
     * @return App
     */
    public function addApp(string $name, string $secret, array $roles, $hashAlgo = PASSWORD_DEFAULT)
    {
        $hash = $hashAlgo === 'md5' ? crypt($secret, '$1$12345678$') : password_hash($secret, PASSWORD_DEFAULT);

        $em = $this->getEm();

        $app = new App();
        $app->setName($name);
        $app->setSecret($hash);
        $em->persist($app);

        foreach ($this->addRoles($roles) as $role) {
            $app->addRole($role);
        }

        $em->flush();

        return $app;
    }
}
