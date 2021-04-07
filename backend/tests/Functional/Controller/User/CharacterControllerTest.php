<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Client;
use Tests\Logger;

class CharacterControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    private $playerId;

    private $corpId = 234;

    private $corpName = 'The Corp.';

    private $corpTicker = '-TTT-';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Logger
     */
    private $log;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->client = new Client();
        $this->log = new Logger('Test');
    }

    public function testShow403()
    {
        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testShow200()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('GET', '/api/user/character/show');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            [
                'id' => 96061222,
                'name' => 'User',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => true,
                'validTokenTime' => '2019-08-03T23:12:45Z',
                'corporation' => null
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testFindCharacter403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-character/abc');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(10); // not an admin but group-manager
        $response2 = $this->runApp('GET', '/api/user/character/find-character/abc');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testFindCharacter200()
    {
        $this->setupDb();
        $this->loginUser(9); // admin

        $response = $this->runApp('GET', '/api/user/character/find-character/ser');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([[
            'character_id' => 456,
            'character_name' => 'Another USER',
            'player_id' => $this->playerId,
            'player_name' => 'User',
        ], [
            'character_id' => 615,
            'character_name' => 'Removed from User',
            'player_id' => $this->playerId,
            'player_name' => 'User',
        ], [
            'character_id' => 96061222,
            'character_name' => 'User',
            'player_id' => $this->playerId,
            'player_name' => 'User',
        ], [
            'character_id' => 96061222,
            'character_name' => "User's previous name",
            'player_id' => $this->playerId,
            'player_name' => 'User',
        ]], $this->parseJsonBody($response));
    }

    public function testFindPlayer403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-player/abc');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(96061222); // not group-manager or admin
        $response2 = $this->runApp('GET', '/api/user/character/find-player/abc');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testFindPlayer200()
    {
        $this->setupDb();
        $this->loginUser(10); // group-manager

        $response = $this->runApp('GET', '/api/user/character/find-player/ser');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([[
            'character_id' => 96061222,
            'character_name' => 'User',
            'player_id' => $this->playerId,
            'player_name' => 'User'
        ]], $this->parseJsonBody($response));
    }

    public function testUpdate403()
    {
        $response = $this->runApp('PUT', '/api/user/character/96061222/update');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdate403_OtherChar()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/character/96061222/update');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdate404()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $response = $this->runApp('PUT', '/api/user/character/9/update');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdate503()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $this->client->setResponse(new Response(500));

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(503, $response->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testUpdate204()
    {
        list($token) = Helper::generateToken();
        $this->setupDb($token);
        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": '.$this->corpId.'
            }'),
            new Response(200, [], '[{
                "character_id": 96061222,
                "corporation_id": '.$this->corpId.'
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertSame(0, count($this->log->getHandler()->getRecords()));

        // check that char was deleted (because owner hash changed)
        $this->helper->getObjectManager()->clear();
        $char = (new RepositoryFactory($this->helper->getObjectManager()))->getCharacterRepository()->find(96061222);
        $this->assertNull($char);
    }

    /**
     * @throws \Exception
     */
    public function testUpdate200_LoggedInUser()
    {
        list($token) = Helper::generateToken(['scope1'], 'Old Name', 'coh1');
        $this->setupDb($token);
        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], /* creates a new CharacterNameChange */ '{
                "name": "Char 96061222",
                "corporation_id": '.$this->corpId.'
            }'),
            new Response(200, [], '[{
                "character_id": 96061222,
                "corporation_id": '.$this->corpId.'
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            ClientInterface::class => $this->client
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $expected = [
            'id' => 96061222,
            'name' => 'Char 96061222',
            'main' => true,
            'created' => null,
            'validToken' => true,
            'validTokenTime' => '2019-08-03T23:12:45Z',
            'corporation' => [
                'id' => $this->corpId,
                'name' => 'The Corp updated.',
                'ticker' => 'TICK',
                'alliance' => null
            ]
        ];
        $actual = $this->parseJsonBody($response);

        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/',
            $actual['lastUpdate']
        );
        unset($actual['lastUpdate']);

        $this->assertSame($expected, $actual);

        // check group
        $this->helper->getObjectManager()->clear();
        $player = (new RepositoryFactory($this->helper->getObjectManager()))
            ->getPlayerRepository()->find($this->playerId);
        $this->assertSame('auto.bni', $player->getGroups()[0]->getName());

        // check char, corp, name change
        $this->assertSame(96061222, $player->getCharacters()[1]->getId());
        $this->assertSame('The Corp updated.', $player->getCharacters()[1]->getCorporation()->getName());
        $this->assertTrue($player->getCharacters()[1]->getValidToken());
        $nameChanges = $player->getCharacters()[1]->getCharacterNameChanges();
        $this->assertSame(3, count($nameChanges)); // 1 from setup, 1 from test
        if ($nameChanges[0]->getOldName() === 'User') {
            // ordered by date, which can be the same for the first 2
            $this->assertSame('User', $nameChanges[0]->getOldName()); // from ESI update
            $this->assertSame('Old Name', $nameChanges[1]->getOldName()); // from access token check (token from setup)
        } else {
            $this->assertSame('Old Name', $nameChanges[0]->getOldName());
            $this->assertSame('User', $nameChanges[1]->getOldName());
        }
        $this->assertSame("User's previous name", $nameChanges[2]->getOldName()); // from setup
    }

    public function testUpdate200_Admin()
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
                "corporation_id": '.$this->corpId.'
            }]'), // affiliation
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-TTT-",
                "alliance_id": null
            }'),
            new Response(200, [], '{}') // for OAuthTestProvider->getResourceOwner()
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            ClientInterface::class => $this->client
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function setupDb(string $token = null): void
    {
        $this->helper->emptyDb();
        $char = $this->helper->addCharacterMain('User', 96061222, [Role::USER]);
        $char->setValidToken(true)->setValidTokenTime(new \DateTime('2019-08-03 23:12:45'))
            ->setCharacterOwnerHash('coh1');
        if ($token) {
            $char->setAccessToken($token);
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

        $this->helper->getObjectManager()->persist($corp);
        $this->helper->getObjectManager()->persist($removedChar);
        $this->helper->getObjectManager()->persist($renamed);
        $this->helper->getObjectManager()->flush();
    }
}
