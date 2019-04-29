<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Character;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;
use Tests\Client;
use Tests\WriteErrorListener;

class OAuthTokenTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $es;

    /**
     * @var OAuthToken
     */
    private $esError;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->helper->addRoles([Role::USER]);

        $this->em = $this->helper->getEm();

        $this->log = new Logger('Test');

        $this->client = new Client();
        $oauth = new OAuthProvider($this->client);

        $this->es = new OAuthToken($oauth, new ObjectManager($this->em, $this->log), $this->log);

        // a second OAuthToken instance with another entity manager that throws an exception on flush.
        $em = $this->helper->getEm(true);
        $em->getEventManager()->addEventListener(Events::onFlush, new WriteErrorListener());
        $this->esError = new OAuthToken($oauth, new ObjectManager($em, $this->log), $this->log);
    }

    /**
     * @throws \Exception
     */
    public function testRefreshAccessTokenNewTokenException()
    {
        $this->client->setResponse(new Response(500));

        $token = new AccessToken([
            'access_token' => 'at',
            'refresh_token' => '',
            'expires' => 1349067601 // 2012-10-01 + 1
        ]);

        $tokenResult = $this->es->refreshAccessToken($token);

        $this->assertSame($token, $tokenResult);
        $this->assertStringStartsWith(
            'An OAuth server error ',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testRefreshAccessTokenIdentityProviderException()
    {
        $this->client->setResponse(new Response(400, [], '{
            "error": "invalid_token",
            "error_description": "The refresh token is expired."
        }'));

        $token = new AccessToken([
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires' => 1349067601 // 2012-10-01 + 1
        ]);

        $this->es->refreshAccessToken($token);
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testRefreshAccessTokenNoRefresh()
    {
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_request"}'));

        $token = new AccessToken([
            'access_token' => 'old-token',
            'refresh_token' => null,
            'expires' => time() - 10000
        ]);

        $this->es->refreshAccessToken($token);
    }

    /**
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testRefreshAccessTokenNotExpired()
    {
        $token = new AccessToken([
            'access_token' => 'old-token',
            'refresh_token' => 're-tk',
            'expires' => time() + 10000
        ]);

        $this->assertSame('old-token', $this->es->refreshAccessToken($token)->getToken());
    }

    /**
     * @throws \Exception
     */
    public function testRefreshAccessTokenNewToken()
    {
        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = new AccessToken([
            'access_token' => 'old-token',
            'refresh_token' => '',
            'expires' => 1519933545 // 03/01/2018 @ 7:45pm (UTC)
        ]);

        $tokenResult = $this->es->refreshAccessToken($token);

        $this->assertNotSame($token, $tokenResult);
        $this->assertSame('new-token', $tokenResult->getToken());
    }

    public function testGetTokenNoExistingTokenException()
    {
        $token = $this->es->getToken(new Character());

        $this->assertSame('', $token);

        // from Exception in createAccessTokenFromCharacter()
        $this->assertSame(
            'Required option not passed: "access_token"',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGetTokenInvalidToken()
    {
        $char = new Character();
        $char->setId(123);
        $char->setName('n');
        $char->setMain(true);
        $char->setCharacterOwnerHash('coh');
        $char->setAccessToken('old-token');
        $char->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        // response for refreshAccessToken()
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_token"}'));

        $token = $this->es->getToken($char);

        $this->assertSame('', $token);
    }

    public function testGetTokenNewTokenUpdateDatabaseOk()
    {
        $char = new Character();
        $char->setId(123);
        $char->setName('n');
        $char->setMain(true);
        $char->setCharacterOwnerHash('coh');
        $char->setAccessToken('old-token');
        $char->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->getToken($char);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getAccessToken());

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertSame('new-token', $charFromDB->getAccessToken());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
    }

    public function testGetTokenNewTokenUpdateDatabaseError()
    {
        $c = new Character();
        $c->setId(123);
        $c->setName('n');
        $c->setMain(true);
        $c->setCharacterOwnerHash('coh');
        $c->setAccessToken('old-token');
        $c->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new-token",
            "refresh_token": "",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->esError->getToken($c);

        $this->assertSame('', $token);
    }

    public function testGetResourceOwnerError()
    {
        $this->client->setResponse(
            // for getResourceOwner()
            new Response(500)
        );

        $owner = $this->es->getResourceOwner(new AccessToken([
            'access_token' => 'at',
            'expires' => time() - 1800,
            'refresh_token' => 'rt',
        ]));

        $this->assertNull($owner);
    }

    public function testGetResourceOwner()
    {
        $this->client->setResponse(
            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterID": "123",
                "CharacterName": "char name",
                "ExpiresOn": "2018-05-03T20:27:38.7999223",
                "CharacterOwnerHash": "coh"
            }')
        );

        $owner = $this->es->getResourceOwner(new AccessToken([
            'access_token' => 'at',
            'expires' => time() - 1800,
            'refresh_token' => 'rt',
        ]));

        $this->assertInstanceOf(ResourceOwnerInterface::class, $owner);
        $this->assertSame([
            'CharacterID' => '123',
            'CharacterName' => 'char name',
            'ExpiresOn' => '2018-05-03T20:27:38.7999223',
            'CharacterOwnerHash' => 'coh',
        ], $owner->toArray());
    }
}
