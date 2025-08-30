<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Client;
use Tests\HttpClientFactory;
use Tests\Logger;

class CharacterControllerTest extends WebTestCase
{
    private Helper $helper;

    private int $playerId;

    private int $corpId = 234;

    private string $corpName = 'The Corp.';

    private string $corpTicker = '-TTT-';

    private Client $client;

    private Logger $log;

    private RepositoryFactory $repoFactory;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->client = new Client();
        $this->log = new Logger();
        $this->repoFactory = (new RepositoryFactory($this->helper->getObjectManager()));
    }

    public function testShow403(): void
    {
        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(403, $response?->getStatusCode());
    }

    public function testShow200(): void
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(200, $response?->getStatusCode());

        $this->assertSame(
            [
                'id' => 96061222,
                'name' => 'User',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => true,
                'validTokenTime' => '2019-08-03T23:12:45Z',
                'tokenLastChecked' => null,
                'corporation' => null,
            ],
            $this->parseJsonBody($response),
        );
    }

    public function testFindCharacter403(): void
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-character/abc');
        $this->assertSame(403, $response1?->getStatusCode());

        $this->loginUser(10); // not an admin but group-manager
        $response2 = $this->runApp('GET', '/api/user/character/find-character/abc');
        $this->assertSame(403, $response2?->getStatusCode());
    }

    public function testFindCharacter200(): void
    {
        $this->setupDb();
        $this->loginUser(9); // admin

        $response1 = $this->runApp('GET', '/api/user/character/find-character/ser');
        $this->assertSame(200, $response1?->getStatusCode());
        $this->assertSame([[
            'characterId' => 456,
            'characterName' => 'Another USER',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ], [
            'characterId' => 615,
            'characterName' => 'Removed from User',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ], [
            'characterId' => 96061222,
            'characterName' => 'User',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ], [
            'characterId' => 96061222,
            'characterName' => "User's previous name",
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ]], $this->parseJsonBody($response1));

        $response2 = $this->runApp('GET', '/api/user/character/find-character/ser?currentOnly=true');
        $this->assertSame([[
            'characterId' => 456,
            'characterName' => 'Another USER',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ], [
            'characterId' => 96061222,
            'characterName' => 'User',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ]], $this->parseJsonBody($response2));
    }

    public function testFindCharacter200_Plugins(): void
    {
        $this->setupDb();
        $this->loginUser(9); // admin

        $response = $this->runApp(
            'GET',
            '/api/user/character/find-character/lug?plugin=true',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/CharacterController']],
        );

        $this->assertSame(200, $response?->getStatusCode());
        $this->assertSame([[
            'characterId' => 456,
            'characterName' => 'Another USER',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ]], $this->parseJsonBody($response));
    }

    public function testFindPlayer403(): void
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-player/abc');
        $this->assertSame(403, $response1?->getStatusCode());

        $this->loginUser(96061222); // not group-manager or admin
        $response2 = $this->runApp('GET', '/api/user/character/find-player/abc');
        $this->assertSame(403, $response2?->getStatusCode());
    }

    public function testFindPlayer200(): void
    {
        $this->setupDb();
        $this->loginUser(10); // group-manager

        $response = $this->runApp('GET', '/api/user/character/find-player/ser');
        $this->assertSame(200, $response?->getStatusCode());

        $this->assertSame([[
            'characterId' => 96061222,
            'characterName' => 'User',
            'playerId' => $this->playerId,
            'playerName' => 'User',
        ]], $this->parseJsonBody($response));
    }

    public function testUpdate403(): void
    {
        $response = $this->runApp('PUT', '/api/user/character/96061222/update');
        $this->assertEquals(403, $response?->getStatusCode());
    }

    public function testUpdate403_OtherChar(): void
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/character/96061222/update');
        $this->assertEquals(403, $response?->getStatusCode());
    }

    public function testUpdate404(): void
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('PUT', '/api/user/character/9/update');
        $this->assertEquals(404, $response?->getStatusCode());
    }

    public function testUpdate503(): void
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $this->client->setResponse(new Response(500));

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $this->assertEquals(503, $response?->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testUpdate204(): void
    {
        list($token) = Helper::generateToken();
        $this->setupDb($token);
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);

        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": ' . $this->corpId . '
            }'),
            new Response(200, [], '[{
                "character_id": 96061222,
                "corporation_id": ' . $this->corpId . '
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $this->assertEquals(204, $response?->getStatusCode());
        $this->assertSame(0, count((array) $this->log->getHandler()?->getRecords()));

        $this->helper->getObjectManager()->clear();

        // check that char was deleted (because the owner hash changed)
        $this->assertNull($this->repoFactory->getCharacterRepository()->find(96061222));

        // there would be a group if the character 96061222 were not deleted, see also next test
        $this->assertSame([], $this->repoFactory->getPlayerRepository()->find($this->playerId)?->getGroupIds());
    }

    /**
     * @throws \Exception
     */
    public function testUpdate200_LoggedInUser(): void
    {
        list($token) = Helper::generateToken(['scope1'], 'Old Name', 'coh1');
        $this->setupDb($token);
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], /* creates a new CharacterNameChange */ '{
                "name": "Char 96061222",
                "corporation_id": ' . $this->corpId . '
            }'),
            new Response(200, [], '[{
                "character_id": 96061222,
                "corporation_id": ' . $this->corpId . '
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
        ]);

        $this->assertEquals(200, $response?->getStatusCode());

        $this->assertSame(1, $this->parseJsonBody($response));

        // check group
        $this->helper->getObjectManager()->clear();
        $player = $this->repoFactory->getPlayerRepository()->find($this->playerId);
        $this->assertSame('auto.bni', $player?->getGroups()[0]->getName());

        // check char, corp, name change
        $char1 = $player->getCharacters()[1];
        $this->assertSame(96061222, $char1->getId());
        $this->assertSame('Char 96061222', $char1->getName());
        $this->assertSame('The Corp updated.', $char1->getCorporation()?->getName());
        $this->assertTrue($char1->getEsiToken(EveLogin::NAME_DEFAULT)?->getValidToken());
        $nameChanges = $char1->getCharacterNameChanges();
        $this->assertSame(2, count($nameChanges)); // only 2 because there are no more changes via ESI token
        $this->assertSame('User', $nameChanges[0]->getOldName()); // from ESI update
        $this->assertSame("User's previous name", $nameChanges[1]->getOldName()); // from setup
    }

    public function testUpdate200_Admin(): void
    {
        $this->setupDb();
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $this->loginUser(9);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": 456
            }'),
            new Response(200, [], '[{
                "character_id": 96061222,
                "corporation_id": ' . $this->corpId . '
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-TTT-",
                "alliance_id": null
            }'),
            new Response(200, [], '{}'), // for OAuthTestProvider->getResourceOwner()
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
        ]);

        $this->assertEquals(200, $response?->getStatusCode());
    }

    public function testAdd_403(): void
    {
        $this->setupDb();

        $response = $this->runApp('POST', '/api/user/character/add/456789');
        $this->assertEquals(403, $response?->getStatusCode());

        $this->loginUser(96061222);
        $response = $this->runApp('POST', '/api/user/character/add/456789');
        $this->assertEquals(403, $response?->getStatusCode());
    }

    public function testAdd_500NoRole(): void
    {
        $this->helper->emptyDb();
        $this->helper->addCharacterMain('Admin', 9, [Role::USER_ADMIN]);
        $this->loginUser(9);

        $response = $this->runApp('POST', '/api/user/character/add/456789');

        $this->assertEquals(500, $response?->getStatusCode());
        $this->assertSame('Could not find user role.', $this->parseJsonBody($response));
    }

    public function testAdd_409(): void
    {
        $this->setupDb();
        $this->loginUser(9);

        $response = $this->runApp('POST', '/api/user/character/add/96061222');

        $this->assertEquals(409, $response?->getStatusCode());
        $this->assertSame('Character already exists.', $this->parseJsonBody($response));
    }

    public function testAdd_404(): void
    {
        $this->setupDb();
        $this->loginUser(9);

        $this->client->setResponse(new Response(404, [], '{"error": "Character not found"}'));

        $response = $this->runApp('POST', '/api/user/character/add/456789', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
        ]);

        $this->assertSame(404, $response?->getStatusCode());
        $this->assertSame('Character not found.', $this->parseJsonBody($response));
    }

    public function testAdd_500(): void
    {
        $this->setupDb();
        $this->loginUser(9);

        $this->client->setResponse(new Response(400));

        $response = $this->runApp('POST', '/api/user/character/add/456789', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
            LoggerInterface::class => $this->log,
        ]);

        $this->assertSame(500, $response?->getStatusCode());
    }

    public function testAdd_201(): void
    {
        $this->setupDb();
        $this->loginUser(9);

        $this->client->setResponse(new Response(200, [], '{"name": "Char 456789"}'));

        $response = $this->runApp('POST', '/api/user/character/add/456789', [], [], [
            HttpClientFactoryInterface::class => new HttpClientFactory($this->client),
        ]);

        $this->assertSame(201, $response?->getStatusCode());
        $this->assertSame('', $response->getBody()->__toString());

        $char = $this->repoFactory->getCharacterRepository()->find(456789);
        $this->assertSame('Char 456789', $char?->getName());
        $this->assertTrue($char->getMain());
        $this->assertSame('Char 456789', $char->getPlayer()->getName());
        $this->assertSame([Role::USER], $char->getPlayer()->getRoleNames());
    }

    private function setupDb(?string $token = null): void
    {
        $this->helper->emptyDb();
        $char = $this->helper->addCharacterMain('User', 96061222, [Role::USER])->setCharacterOwnerHash('coh1');
        $char->getEsiToken(EveLogin::NAME_DEFAULT)?->setValidToken(true)
            ->setValidTokenTime(new \DateTime('2019-08-03 23:12:45'));
        if ($token) {
            $this->helper->createOrUpdateEsiToken($char, 123456, $token);
        }
        $this->helper->addCharacterToPlayer('Another USER', 456, $char->getPlayer());
        $this->playerId = $char->getPlayer()->getId();
        $this->helper->addCharacterMain('Admin', 9, [Role::USER, Role::USER_ADMIN]);
        $this->helper->addCharacterMain('Manager', 10, [Role::GROUP_MANAGER]);

        $groups = $this->helper->addGroups(['auto.bni']);

        $corp = (new Corporation())->setId($this->corpId)->setName($this->corpName)->setTicker($this->corpTicker);
        $corp->addGroup($groups[0]);

        $removedChar = (new RemovedCharacter())
            ->setCharacterId(615)
            ->setCharacterName('Removed from User')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_DELETED_MANUALLY);
        $removedChar->setPlayer($char->getPlayer());
        $renamed = (new CharacterNameChange())
            ->setCharacter($char)
            ->setOldName("User's previous name")
            ->setChangeDate(new \DateTime('2021-04-07 15:07:00'));

        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin';
        $conf->active = true;
        $service = (new Plugin())->setName('S1')->setConfigurationDatabase($conf);

        $this->helper->getObjectManager()->persist($corp);
        $this->helper->getObjectManager()->persist($removedChar);
        $this->helper->getObjectManager()->persist($renamed);
        $this->helper->getObjectManager()->persist($service);
        $this->helper->getObjectManager()->flush();
    }
}
