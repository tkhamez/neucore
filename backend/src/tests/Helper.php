<?php declare(strict_types=1);

namespace Tests;

use Brave\Core\Application;
use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Repository\GroupRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
use Brave\Core\Repository\RoleRepository;
use Brave\Slim\Session\SessionData;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;

class Helper
{
    /**
     * @var EntityManagerInterface
     */
    private static $em;

    private $entities = [
        App::class,
        Character::class,
        Player::class,
        Group::class,
        Role::class,
        Corporation::class,
        Alliance::class,
    ];

    public function resetSessionData(): void
    {
        unset($_SESSION);

        $rp = new \ReflectionProperty(SessionData::class, 'sess');
        $rp->setAccessible(true);
        $rp->setValue(null, null);

        $rp = new \ReflectionProperty(SessionData::class, 'readOnly');
        $rp->setAccessible(true);
        $rp->setValue(null, true);
    }

    public function getEm(bool $discrete = false): EntityManagerInterface
    {
        if (self::$em === null || $discrete) {
            $settings = (new Application())->loadSettings(true);

            $config = Setup::createAnnotationMetadataConfiguration(
                $settings['config']['doctrine']['meta']['entity_paths'],
                $settings['config']['doctrine']['meta']['dev_mode'],
                $settings['config']['doctrine']['meta']['proxy_dir']
            );

            $em = EntityManager::create($settings['config']['doctrine']['connection'], $config);

            if ($discrete) {
                return $em;
            } else {
                self::$em = $em;
            }
        }

        return self::$em;
    }

    public function updateDbSchema(): void
    {
        $em = $this->getEm();

        $classes = [];
        foreach ($this->entities as $entity) {
            $classes[] = $em->getClassMetadata($entity);
        }

        $tool = new SchemaTool($em);
        $tool->updateSchema($classes);
    }

    public function emptyDb(): void
    {
        $em = $this->getEm();
        $qb = $em->createQueryBuilder();

        foreach ($this->entities as $entity) {
            $qb->delete($entity)->getQuery()->execute();
        }

        $em->clear();
    }

    /**
     * @param array $roles
     * @return \Brave\Core\Entity\Role[]
     */
    public function addRoles(array $roles): array
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
     * @param array $groups
     * @return \Brave\Core\Entity\Group[]
     */
    public function addGroups(array $groups): array
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

    public function addCharacterMain(string $name, int $charId, array $roles = [], array $groups = []): Character
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

    public function addCharacterToPlayer(string $name, int $charId, Player $player): Character
    {
        $alt = new Character();
        $alt->setId($charId);
        $alt->setName($name);
        $alt->setMain(false);
        $alt->setCharacterOwnerHash('456');
        $alt->setAccessToken('def');
        $alt->setPlayer($player);
        $player->addCharacter($alt);

        $this->getEm()->persist($alt);
        $this->getEm()->flush();

        return $alt;
    }

    public function addApp(string $name, string $secret, array $roles, $hashAlgo = PASSWORD_DEFAULT): App
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
