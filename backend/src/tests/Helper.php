<?php declare(strict_types=1);

namespace Tests;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Neucore\Application;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Slim\Session\SessionData;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\DBAL\DBALException;
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

    private static $roleSequence = 0;

    private $entities = [
        GroupApplication::class,
        App::class,
        CorporationMember::class,
        Character::class,
        RemovedCharacter::class,
        Player::class,
        Group::class,
        Role::class,
        Corporation::class,
        Alliance::class,
        SystemVariable::class,
    ];

    /**
     * @throws \Exception
     */
    public static function generateToken(
        array $scopes = ['scope1', 'scope2'],
        string $charName = 'Name',
        string $ownerHash = 'hash',
        string $ownerHashKey = 'owner'
    ): array {
        // create key
        $jwk = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig']);

        // create token
        $algorithmManager = AlgorithmManager::create([new RS256()]);
        $jwsBuilder = new JWSBuilder(null, $algorithmManager);
        $payload = (string)json_encode([
            'scp' => count($scopes) > 1 ? $scopes : ($scopes[0] ?? null),
            'sub' => 'CHARACTER:EVE:123',
            'name' => $charName,
            $ownerHashKey => $ownerHash,
            'exp' => time() + 3600,
            'iss' => 'login.eveonline.com',
        ]);
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => $jwk->get('alg')])
            ->build();
        $token = (new CompactSerializer())->serialize($jws);

        // create key set
        $keySet = [$jwk->toPublic()->jsonSerialize()];

        return [$token, $keySet];
    }

    /**
     * @throws \Exception
     */
    public static function parseToken(string $token)
    {
        $serializerManager = JWSSerializerManager::create([new CompactSerializer()]);
        $jws = $serializerManager->unserialize($token);
        return json_decode((string)$jws->getPayload(), true);
    }

    public function resetSessionData(): void
    {
        unset($_SESSION);
        (new SessionData())->setReadOnly(true);
    }

    public function getEm(bool $discrete = false): EntityManagerInterface
    {
        if (self::$em === null || $discrete) {
            $conf = (new Application())->loadSettings(true)['doctrine'];

            $config = Setup::createAnnotationMetadataConfiguration(
                $conf['meta']['entity_paths'],
                $conf['meta']['dev_mode'],
                $conf['meta']['proxy_dir'],
                null,
                false
            );
            /* @phan-suppress-next-line PhanDeprecatedFunction */
            AnnotationRegistry::registerLoader('class_exists');

            $em = EntityManager::create($conf['connection'], $config);

            if ($discrete) {
                return $em;
            } else {
                self::$em = $em;
            }
        }

        return self::$em;
    }

    /**
     * @throws DBALException
     */
    public function updateDbSchema(): void
    {
        $em = $this->getEm();

        $classes = [];
        foreach ($this->entities as $entity) {
            $classes[] = $em->getClassMetadata($entity);
        }

        $tool = new SchemaTool($em);
        $em->getConnection()->exec('SET FOREIGN_KEY_CHECKS = 0;');
        $tool->updateSchema($classes);
        $em->getConnection()->exec('SET FOREIGN_KEY_CHECKS = 1;');
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
     * @return Role[]
     */
    public function addRoles(array $roles): array
    {
        $em = $this->getEm();
        $rr = (new RepositoryFactory($em))->getRoleRepository();

        $roleEntities = [];
        foreach ($roles as $roleName) {
            $role = $rr->findOneBy(['name' => $roleName]);
            if ($role === null) {
                self::$roleSequence ++;
                $role = new Role(self::$roleSequence);
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
     * @return Group[]
     */
    public function addGroups(array $groups): array
    {
        $em = $this->getEm();
        $gr = (new RepositoryFactory($em))->getGroupRepository();

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

    public function addNewPlayerToCharacterAndFlush(Character $character)
    {
        $player = (new Player())->setName('Player');
        $character->setPlayer($player);
        $this->getEm()->persist($player);
        $this->getEm()->persist($character);
        $this->getEm()->flush();
    }

    public function addApp(string $name, string $secret, array $roles, $hashAlgorithm = PASSWORD_BCRYPT): App
    {
        $hash = $hashAlgorithm === 'md5' ? crypt($secret, '$1$12345678$') : password_hash($secret, $hashAlgorithm);

        $app = new App();
        $app->setName($name);
        $app->setSecret((string) $hash);
        $this->getEm()->persist($app);

        foreach ($this->addRoles($roles) as $role) {
            $app->addRole($role);
        }

        $this->getEm()->flush();

        return $app;
    }
}
