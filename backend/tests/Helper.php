<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Eve\Sso\AuthenticationProvider;
use GuzzleHttp\Psr7\Response;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Neucore\Application;
use Neucore\Container;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\AppRequests;
use Neucore\Entity\Character;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EsiType;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\AutoGroupAssignment;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\OAuthToken;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;

class Helper
{
    private static ?EntityManagerInterface $em = null;

    private static int $roleSequence = 0;

    private array $entities = [
        Service::class,
        Watchlist::class,
        GroupApplication::class,
        AppRequests::class,
        App::class,
        CorporationMember::class,
        CharacterNameChange::class,
        EsiToken::class,
        EveLogin::class,
        Character::class,
        RemovedCharacter::class,
        PlayerLogins::class,
        Player::class,
        Group::class,
        Role::class,
        Corporation::class,
        Alliance::class,
        SystemVariable::class,
        EsiType::class,
        EsiLocation::class,
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
        $algorithmManager = new AlgorithmManager([new RS256()]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
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

    public function resetSessionData(): void
    {
        unset($_SESSION);
        SessionData::setReadOnly(true);
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->getEm();
    }

    public function getEm(): EntityManagerInterface
    {
        if (self::$em === null) {
            // Don't build the container here to get the EntityManager, because that roughly
            // doubles the time it takes to run all the tests (with sqlite memory db).
            $config = (new Application())->loadSettings(true);
            self::$em = Container::getDefinitions()[EntityManagerInterface::class](null, $config);
        }

        /* @phan-suppress-next-line PhanTypeMismatchReturnNullable */
        return self::$em;
    }

    public function getAuthenticationProvider(Client $client): AuthenticationProvider {
        $authProvider = new AuthenticationProvider([
            'clientId' => '123',
            'clientSecret' => 'abc',
            'redirectUri' => 'http',
            'urlAuthorize' => 'http',
            'urlAccessToken' => 'http',
            'urlResourceOwnerDetails' => '',
            'urlKeySet' => '',
            'urlRevoke' => 'http',
        ]);
        $authProvider->getProvider()->setHttpClient($client);
        return $authProvider;
    }

    public function getAccountService(Logger $logger, Client $client): Account
    {
        $repoFactory = RepositoryFactory::getInstance($this->getObjectManager());
        $objectManager = new \Neucore\Service\ObjectManager($this->getObjectManager(), $logger);
        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);
        $characterService = new \Neucore\Service\Character($objectManager, $repoFactory);
        $esiApiFactory = new EsiApiFactory($client, $config);
        $esiData = new EsiData($logger, $esiApiFactory, $objectManager, $repoFactory, $characterService, $config);
        $autoGroups = new AutoGroupAssignment($repoFactory);
        $token = new OAuthToken($this->getAuthenticationProvider($client), $objectManager, $logger);
        return new Account($logger, $objectManager, $repoFactory, $esiData, $autoGroups, $token);
    }

    public function getUserAuthService(Logger $logger, Client $client): UserAuth
    {
        $repoFactory = RepositoryFactory::getInstance($this->getObjectManager());
        $objectManager = new \Neucore\Service\ObjectManager($this->getObjectManager(), $logger);
        $accountService = $this->getAccountService($logger, $client);
        return new UserAuth(new SessionData(), $accountService, $objectManager, $repoFactory, $logger);
    }

    public function getDbName(): string
    {
        try {
            $connection = $this->getEm()->getConnection()->getDatabasePlatform();
        } catch (Exception $e) {
            return 'error';
        }
        if ($connection instanceof SqlitePlatform) {
            return 'sqlite';
        } elseif ($connection instanceof MySQLPlatform) {
            return 'mysql';
        } else {
            return 'other';
        }
    }

    public function addEm(array $mocks): array
    {
        if (! array_key_exists(ObjectManager::class, $mocks)) {
            $mocks[ObjectManager::class] = (new self())->getEm();
        }
        if (! array_key_exists(EntityManagerInterface::class, $mocks)) {
            $mocks[EntityManagerInterface::class] = (new self())->getEm();
        }

        return $mocks;
    }

    /**
     * @throws Exception
     */
    public function updateDbSchema(): void
    {
        $em = $this->getEm();

        $classes = [];
        foreach ($this->entities as $entity) {
            $classes[] = $em->getClassMetadata($entity);
        }

        $tool = new SchemaTool($em);
        if ($this->getDbName() === 'sqlite') {
            $tool->updateSchema($classes);
        } else {
            $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');
            $tool->updateSchema($classes);
            $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
        }
    }

    public function emptyDb(): void
    {
        $em = $this->getEm();
        $qb = $em->createQueryBuilder();

        foreach ($this->entities as $entity) {
            $qb->delete($entity)->getQuery()->execute();
        }

        if ($this->getDbName() === 'sqlite') {
            // for some reason these relation tables are not empties with SQLite in-memory db
            try {
                $em->getConnection()->executeStatement('DELETE FROM watchlist_corporation WHERE 1');
                $em->getConnection()->executeStatement('DELETE FROM watchlist_alliance WHERE 1');
                $em->getConnection()->executeStatement('DELETE FROM watchlist_kicklist_corporation WHERE 1');
                $em->getConnection()->executeStatement('DELETE FROM watchlist_kicklist_alliance WHERE 1');
                $em->getConnection()->executeStatement('DELETE FROM watchlist_allowlist_corporation WHERE 1');
                $em->getConnection()->executeStatement('DELETE FROM watchlist_allowlist_alliance WHERE 1');
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        $em->clear();
    }

    /**
     * @param array $roles
     * @return Role[]
     */
    public function addRoles(array $roles): array
    {
        $om = $this->getObjectManager();
        $rr = RepositoryFactory::getInstance($om)->getRoleRepository();

        $roleEntities = [];
        foreach ($roles as $roleName) {
            $role = $rr->findOneBy(['name' => $roleName]);
            if ($role === null) {
                self::$roleSequence ++;
                $role = new Role(self::$roleSequence);
                $role->setName($roleName);
                $om->persist($role);
            }
            $roleEntities[] = $role;
        }
        $om->flush();

        return $roleEntities;
    }

    /**
     * @param array $groups
     * @return Group[]
     */
    public function addGroups(array $groups): array
    {
        $om = $this->getObjectManager();
        $gr = RepositoryFactory::getInstance($om)->getGroupRepository();

        $groupEntities = [];
        foreach ($groups as $groupName) {
            $group = $gr->findOneBy(['name' => $groupName]);
            if ($group === null) {
                $group = new Group();
                $group->setName($groupName);
                $om->persist($group);
            }
            $groupEntities[] = $group;
        }
        $om->flush();

        return $groupEntities;
    }

    public function addCharacterMain(
        string $name,
        int $charId,
        array $roles = [],
        array $groups = [],
        bool $withEsiToken = true,
        ?\DateTime $created = null
    ): Character {
        $om = $this->getObjectManager();

        $player = new Player();
        $player->setName($name);
        $om->persist($player);

        $char = new Character();
        $char->setId($charId);
        $char->setName($name);
        $char->setMain(true);
        $char->setCharacterOwnerHash('123');
        if ($created) {
            $char->setCreated($created);
        }
        $om->persist($char);

        $char->setPlayer($player);
        $player->addCharacter($char);

        if ($withEsiToken) {
            $this->createOrUpdateEsiToken($char);
        }

        foreach ($this->addRoles($roles) as $role) {
            $player->addRole($role);
        }

        foreach ($this->addGroups($groups) as $group) {
            $player->addGroup($group);
        }

        $om->flush();

        return $char;
    }

    public function addCharacterToPlayer(
        string $name,
        int $charId,
        Player $player,
        bool $withEsiToken = false
    ): Character {
        $alt = new Character();
        $alt->setId($charId);
        $alt->setName($name);
        $alt->setMain(false);
        $alt->setCharacterOwnerHash('456');
        $alt->setPlayer($player);
        $player->addCharacter($alt);

        $this->getObjectManager()->persist($alt);

        if ($withEsiToken) {
            $this->createOrUpdateEsiToken($alt);
        } else {
            $this->getObjectManager()->flush();
        }

        return $alt;
    }

    public function addNewPlayerToCharacterAndFlush(Character $character): Player
    {
        $player = (new Player())->setName('Player');
        $character->setPlayer($player);
        $this->getObjectManager()->persist($player);
        $this->getObjectManager()->persist($character);
        $this->getObjectManager()->flush();

        return $player;
    }

    public function createOrUpdateEsiToken(
        Character $character,
        int $expires = 123456,
        string $accessToken = 'at',
        ?bool $valid = null
    ): EsiToken {
        $om = $this->getObjectManager();

        $esiToken = $character->getEsiToken(EveLogin::NAME_DEFAULT);
        if ($esiToken === null) {
            $eveLogin = RepositoryFactory::getInstance($om)->getEveLoginRepository()
                ->findOneBy(['name' => EveLogin::NAME_DEFAULT]);
            if ($eveLogin === null) {
                $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
                $om->persist($eveLogin);
            }
            $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setRefreshToken('rt');
            $character->addEsiToken($esiToken);
            $esiToken->setCharacter($character);
        }

        $esiToken->setExpires($expires);
        $esiToken->setAccessToken($accessToken);
        if ($valid !== null) {
            $esiToken->setValidToken($valid);
        }

        $om->persist($esiToken);
        $om->persist($character);
        $om->flush();

        return $esiToken;
    }

    /**
     * @param mixed $hashAlgorithm
     */
    public function addApp(string $name, string $secret, array $roles, $hashAlgorithm = PASSWORD_BCRYPT): App
    {
        $hash = $hashAlgorithm === 'md5' ? crypt($secret, '$1$12345678$') : password_hash($secret, $hashAlgorithm);

        $app = new App();
        $app->setName($name);
        $app->setSecret((string) $hash);
        $this->getObjectManager()->persist($app);

        foreach ($this->addRoles($roles) as $role) {
            $app->addRole($role);
        }

        $this->getObjectManager()->flush();

        return $app;
    }

    public function getGuzzleHandler(Response $response): callable
    {
        return function () use ($response) {
            return new class($response) {
                private Response $response;

                public function __construct(Response $response)
                {
                    $this->response = $response;
                }

                public function then(callable $onFulfilled): void
                {
                    $onFulfilled($this->response);
                }
            };
        };
    }
}
