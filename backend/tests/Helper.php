<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\DBAL\Exception;
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
use Neucore\Entity\Plugin;
use Neucore\Entity\Session;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Factory\EveApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Core\Data;
use Neucore\Plugin\Core\Factory;
use Neucore\Service\Account;
use Neucore\Service\AccountGroup;
use Neucore\Service\AutoGroupAssignment;
use Neucore\Service\Config;
use Neucore\Service\EsiClient;
use Neucore\Service\EsiData;
use Neucore\Service\EveMail;
use Neucore\Service\EveMailToken;
use Neucore\Service\OAuthToken;
use Neucore\Service\PluginService;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Util\Crypto;
use Neucore\Util\Database;
use Symfony\Component\Yaml\Parser;

class Helper
{
    private static ?EntityManagerInterface $em = null;

    private static int $roleSequence = 0;

    private array $entities = [
        Plugin::class,
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
        Session::class,
    ];

    /**
     * @throws \Exception
     */
    public static function generateToken(
        array $scopes = ['scope1', 'scope2'],
        string $charName = 'Name',
        string $ownerHash = 'hash',
        int $charId = 123,
        string $ownerHashKey = 'owner',
    ): array {
        // create key
        $kid = 'JWT-Signature-Key';
        $jwk = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => $kid]);

        // create token
        $algorithmManager = new AlgorithmManager([new RS256()]);
        $jwsBuilder = new JWSBuilder($algorithmManager);
        $payload = (string) json_encode([
            'kid' => $kid,
            'scp' => count($scopes) > 1 ? $scopes : ($scopes[0] ?? null),
            'sub' => "CHARACTER:EVE:$charId",
            'name' => $charName,
            $ownerHashKey => $ownerHash,
            'exp' => time() + 3600,
            'iss' => 'login.eveonline.com',
        ]);
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => $jwk->get('alg'), 'kid' => $kid])
            ->build();
        $token = (new CompactSerializer())->serialize($jws);

        // create key set
        $keySet = [$jwk->toPublic()->jsonSerialize()];

        return [$token, $keySet];
    }

    public static function getAuthenticationProvider(Client $client): AuthenticationProvider
    {
        return new AuthenticationProvider(
            [
                'clientId' => '123',
                'clientSecret' => 'abc',
                'redirectUri' => 'http',
                'urlAuthorize' => 'http',
                'urlAccessToken' => 'http',
                'urlKeySet' => 'http',
                'urlRevoke' => 'http',
                'issuer' => 'localhost',
            ],
            httpClient: $client,
        );
    }

    public static function getPluginFactory(
        ?Client $client = null,
        ?Logger $logger = null,
        ?StorageInterface $storage = null,
    ): Factory {
        if (!$client) {
            $client = new Client();
        }
        if (!$logger) {
            $logger = new Logger();
        }
        $repositoryFactory = new RepositoryFactory(self::getOm());
        if (!$storage) {
            $storage = new SystemVariableStorage(
                $repositoryFactory,
                new \Neucore\Service\ObjectManager(self::getOm(), $logger),
            );
        }
        return new Factory(
            new \Neucore\Plugin\Core\EsiClient(
                self::getEsiClientService($client, $logger),
                new HttpClientFactory($client),
                $storage,
            ),
            new \Neucore\Plugin\Core\Account(
                $repositoryFactory,
                new AccountGroup($repositoryFactory, self::getOm()),
            ),
            new Data($repositoryFactory),
        );
    }

    public static function getEsiClientService(
        Client $client,
        Logger $logger,
        string $compatibilityDate = '',
    ): EsiClient {
        $objectManager = new \Neucore\Service\ObjectManager(self::getOm(), $logger);
        $repoFactory = RepositoryFactory::getInstance(self::getOm());
        return new EsiClient(
            $repoFactory,
            self::getConfig($compatibilityDate),
            new OAuthToken(self::getAuthenticationProvider($client), $objectManager, $logger),
            new HttpClientFactory($client),
            new EveMailToken(
                $repoFactory,
                $objectManager,
                self::getAuthenticationProvider($client),
                $logger,
            ),
        );
    }

    private static function getOm(): EntityManagerInterface
    {
        return self::$em;
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

        return self::$em;
    }

    public function getAccountService(Logger $logger, Client $client, ?Config $config): Account
    {
        $config = $config ?: $this->getConfig();
        $repoFactory = RepositoryFactory::getInstance($this->getObjectManager());
        $objectManager = new \Neucore\Service\ObjectManager($this->getObjectManager(), $logger);
        $characterService = new \Neucore\Service\Character($objectManager, $repoFactory);
        $eveMailToken = new EveMailToken(
            $repoFactory,
            $objectManager,
            self::getAuthenticationProvider($client),
            $logger,
        );
        $esiApiFactory = new EveApiFactory(new HttpClientFactory($client), $config, $eveMailToken);
        $esiData = new EsiData($logger, $esiApiFactory, $objectManager, $repoFactory, $characterService);
        $accountGroup = new AccountGroup($repoFactory, $this->getObjectManager());
        $autoGroups = new AutoGroupAssignment($repoFactory, $accountGroup);
        $token = new OAuthToken(self::getAuthenticationProvider($client), $objectManager, $logger);
        $pluginService = new PluginService(
            $logger,
            $repoFactory,
            $accountGroup,
            $config,
            new Parser(),
            $this->getPluginFactory($client, $logger),
        );
        return new Account($logger, $objectManager, $repoFactory, $esiData, $autoGroups, $token, $pluginService);
    }

    public function getUserAuthService(Logger $logger, Client $client, ?Config $config): UserAuth
    {
        $config = $config ?: $this->getConfig();
        $repoFactory = RepositoryFactory::getInstance($this->getObjectManager());
        $objectManager = new \Neucore\Service\ObjectManager($this->getObjectManager(), $logger);
        $accountService = $this->getAccountService($logger, $client, $config);
        $accountGroupService = new AccountGroup($repoFactory, $this->getObjectManager());
        $eveMailToken = new EveMailToken(
            $repoFactory,
            $objectManager,
            self::getAuthenticationProvider($client),
            $logger,
        );
        return new UserAuth(
            new SessionData(),
            $accountService,
            $accountGroupService,
            $objectManager,
            $repoFactory,
            $logger,
            new EveMail(
                $repoFactory,
                $objectManager,
                new EveApiFactory(new HttpClientFactory($client), $config, $eveMailToken),
                $eveMailToken,
            ),
        );
    }

    public function addEm(array $mocks): array
    {
        if (!array_key_exists(ObjectManager::class, $mocks)) {
            $mocks[ObjectManager::class] = (new self())->getEm();
        }
        if (!array_key_exists(EntityManagerInterface::class, $mocks)) {
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
        if (Database::getDbName($em) === 'sqlite') {
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
            $qb->delete($entity, 'c')->getQuery()->execute();
        }

        if (Database::getDbName($em) === 'sqlite') {
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

        self::$roleSequence = 0;

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
                self::$roleSequence++;
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
        ?\DateTime $created = null,
        int $tokenExpires = 123456,
        ?bool $tokenValid = null,
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
            $this->createOrUpdateEsiToken($char, $tokenExpires, 'at', $tokenValid);
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
        bool $withEsiToken = false,
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

    public function addNewPlayerToCharacterAndFlush(Character $character, string $playerName = 'Player'): Player
    {
        $player = (new Player())->setName($playerName);
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
        ?bool $valid = null,
        string $loginName = EveLogin::NAME_DEFAULT,
        array $scopes = [], // only added if EveLogin does not already exist
        array $roles = [], // only added if EveLogin does not already exist
        ?\DateTime $lastChecked = null,
        string $refreshToken = 'rt',
    ): EsiToken {
        $om = $this->getObjectManager();

        $esiToken = $character->getEsiToken($loginName);
        if ($esiToken === null) {
            $eveLogin = $this->addEveLogin($loginName, $scopes, $roles);
            $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setRefreshToken($refreshToken);
            $character->addEsiToken($esiToken);
            $esiToken->setCharacter($character);
            if (!empty($roles)) {
                $esiToken->setHasRoles(false);
            }
            if ($lastChecked) {
                $esiToken->setLastChecked($lastChecked);
            }
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

    public function addApp(
        string $name,
        string $secret,
        array $roles,
        array $eveLoginNames = [],
        string $hashAlgorithm = Crypto::PASSWORD_HASH,
    ): App {
        $hash = $hashAlgorithm === 'md5' ? crypt($secret, '$1$12345678$') : password_hash($secret, $hashAlgorithm);

        $app = new App();
        $app->setName($name);
        $app->setSecret($hash);
        $this->getObjectManager()->persist($app);

        foreach ($this->addRoles($roles) as $role) {
            $app->addRole($role);
        }

        foreach ($eveLoginNames as $eveLoginName) {
            $eveLogin = $this->addEveLogin($eveLoginName);
            $app->addEveLogin($eveLogin);
        }

        $this->getObjectManager()->flush();

        return $app;
    }

    public function getGuzzleHandler(Response $response): callable
    {
        return function () use ($response) {
            return new class ($response) {
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

    public function setupDeactivateAccount(
        ?Player $player = null,
        int $charId = 100200,
        string $charName = 'Char',
        int $corpId = 101,
        string $corpName = 'Corp',
    ): Character {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1')
            ->setScope(SystemVariable::SCOPE_SETTINGS);
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->getEm()->persist($setting1);
        $this->getEm()->persist($setting2);
        $this->getEm()->persist($setting3);
        $this->getEm()->flush();
        $corporation = (new Corporation())->setId($corpId);
        $login = (new EveLogin())->setId(1);
        $token = (new EsiToken())->setValidToken(false)->setEveLogin($login)
            ->setRefreshToken('')->setAccessToken('')->setExpires(time() - 1000);
        $char = (new Character())->setCorporation($corporation)->addEsiToken($token);

        if ($player) { // persist everything
            $token->setCharacter($char);
            $char->setPlayer($player);
            $corporation->setName($corpName);
            $char->setId($charId)->setName($charName);
            $this->getEm()->persist($corporation);
            $this->getEm()->persist($login);
            $this->getEm()->persist($token);
            $this->getEm()->persist($char);
        }

        return $char;
    }

    public function addEveLogin(string $name, array $scopes = [], array $roles = []): EveLogin
    {
        $om = $this->getObjectManager();

        $eveLogin = RepositoryFactory::getInstance($om)->getEveLoginRepository()->findOneBy(['name' => $name]);
        if ($eveLogin === null) {
            $eveLogin = (new EveLogin())->setName($name)
                ->setEsiScopes(implode(' ', $scopes))
                ->setEveRoles($roles);
            $om->persist($eveLogin);
        }

        return $eveLogin;
    }

    public function deleteDirectory(string $directory): void
    {
        $dir = realpath($directory);
        if (!$dir) {
            return;
        }

        $directory = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { /* @var $file \SplFileInfo */
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    public static function getEveConfig(
        string $compatibilityDate = '',
        string $useMailTokenForUnauthenticatedRequests = '0',
    ): array {
        return [
            'esi_host' => 'http://localhost',
            'esi_compatibility_date' => $compatibilityDate,
            'use_mail_token_for_unauthenticated_requests' => $useMailTokenForUnauthenticatedRequests,
        ];
    }

    public static function getConfig(
        string $compatibilityDate = '',
        string $useMailTokenForUnauthenticatedRequests = '0',
    ): Config {
        return new Config([
            'eve' => self::getEveConfig($compatibilityDate, $useMailTokenForUnauthenticatedRequests),
        ]);
    }
}
