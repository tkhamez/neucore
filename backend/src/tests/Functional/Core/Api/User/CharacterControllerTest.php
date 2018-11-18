<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Role;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\WebTestCase;
use Tests\Helper;
use Tests\OAuthProvider;
use Tests\Client;

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

    public function setUp()
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->client = new Client();
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
                'lastUpdate' => null,
                'validToken' => true,
                'corporation' => null
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testFindBy403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-by/abc');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(96061222); // not an admin
        $response2 = $this->runApp('GET', '/api/user/character/find-by/abc');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testFindBy200()
    {
        $this->setupDb();
        $this->loginUser(9);

        $response = $this->runApp('GET', '/api/user/character/find-by/ser');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(
            [
                ['id' => 456, 'name' => 'Another USER'],
                ['id' => 96061222, 'name' => 'User']

            ],
            $this->parseJsonBody($response)
        );
    }

    public function testFindPlayerOf403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/character/find-player-of/123');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(96061222); // not an admin
        $response2 = $this->runApp('GET', '/api/user/character/find-player-of/123');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testFindPlayerOf204()
    {
        $this->setupDb();
        $this->loginUser(9);

        $response = $this->runApp('GET', '/api/user/character/find-player-of/123');
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testFindPlayerOf200()
    {
        $this->setupDb();
        $this->loginUser(9);

        $response = $this->runApp('GET', '/api/user/character/find-player-of/456');
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->playerId,
            'name' => 'User',
        ], $this->parseJsonBody($response));
    }

    public function testUpdate403()
    {
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
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            LoggerInterface::class => (new Logger('Test'))->pushHandler(new TestHandler())
        ]);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testUpdate204()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": '.$this->corpId.'
            }'),
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
            new Response(200, [], '{"CharacterOwnerHash": "coh2"}') // for getResourceOwner()
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => (new Logger('Test'))->pushHandler(new TestHandler())
        ]);

        $this->assertEquals(204, $response->getStatusCode());

        // check that char was deleted
        $this->helper->getEm()->clear();
        $char = (new RepositoryFactory($this->helper->getEm()))->getCharacterRepository()->find(96061222);
        $this->assertNull($char);
    }

    public function testUpdate200LoggedInUser()
    {
        $this->setupDb();
        $this->loginUser(96061222);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": '.$this->corpId.'
            }'),
            new Response(200, [], '{
                "name": "The Corp updated.",
                "ticker": "TICK",
                "alliance_id": null
            }'),
            // getAccessToken() not called because token is not expired
            new Response(200, [], '{"CharacterOwnerHash": "coh1"}') // for getResourceOwner()
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => (new Logger('Test'))->pushHandler(new TestHandler())
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $expected = [
            'id' => 96061222,
            'name' => 'Char 96061222',
            'main' => true,
            'validToken' => true,
            'corporation' => [
                'id' => $this->corpId,
                'name' => 'The Corp updated.',
                'ticker' => 'TICK',
                'alliance' => null
            ]
        ];
        $actual = $this->parseJsonBody($response);

        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/', $actual['lastUpdate']);
        unset($actual['lastUpdate']);

        $this->assertSame($expected, $actual);

        // check group
        $this->helper->getEm()->clear();
        $player = (new RepositoryFactory($this->helper->getEm()))->getPlayerRepository()->find($this->playerId);
        $this->assertSame('auto.bni', $player->getGroups()[0]->getName());

        // check char, corp
        $this->assertSame(96061222, $player->getCharacters()[1]->getId());
        $this->assertSame('The Corp updated.', $player->getCharacters()[1]->getCorporation()->getName());
        $this->assertTrue($player->getCharacters()[1]->getValidToken());
    }

    public function testUpdate200Admin()
    {
        $this->setupDb();
        $this->loginUser(9);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "Char 96061222",
                "corporation_id": 456
            }'),
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-TTT-",
                "alliance_id": null
            }'),
            new Response(200, [], '{}') // for OAuthTestProvider->getResourceOwner()
        );

        $response = $this->runApp('PUT', '/api/user/character/96061222/update', [], [], [
            EsiApiFactory::class => (new EsiApiFactory())->setClient($this->client),
            GenericProvider::class => new OAuthProvider($this->client),
            LoggerInterface::class => (new Logger('Test'))->pushHandler(new TestHandler())
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    private function setupDb()
    {
        $this->helper->emptyDb();
        $char = $this->helper->addCharacterMain('User', 96061222, [Role::USER]);
        $char->setValidToken(true)->setCharacterOwnerHash('coh1');
        $this->helper->addCharacterToPlayer('Another USER', 456, $char->getPlayer());
        $this->playerId = $char->getPlayer()->getId();
        $this->helper->addCharacterMain('Admin', 9, [Role::USER, Role::USER_ADMIN]);

        $groups = $this->helper->addGroups(['auto.bni']);

        $corp = (new Corporation())->setId($this->corpId)->setName($this->corpName)->setTicker($this->corpTicker);
        $corp->addGroup($groups[0]);

        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->flush();
    }
}
