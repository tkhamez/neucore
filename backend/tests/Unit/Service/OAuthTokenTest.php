<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neucore\Entity\Character;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
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
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

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

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->helper->addRoles([Role::USER]);

        $this->em = $this->helper->getEm();

        $this->log = new Logger('Test');

        $this->client = new Client();
        $config = new Config(['eve' => [
            'datasource' => '',
            'oauth_urls_tq' => ['revoke' => ''],
            'client_id' => '',
            'secret_key' => '',
        ]]);

        $this->es = new OAuthToken(
            new OAuthProvider($this->client),
            new ObjectManager($this->em, $this->log),
            $this->log,
            $this->client,
            $config
        );
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
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
     * @throws IdentityProviderException
     */
    public function testRefreshAccessTokenIdentityProviderException()
    {
        $this->client->setResponse(new Response(400, [], '{ "error": "invalid_grant" }'));

        $token = new AccessToken([
            'access_token' => 'at',
            'refresh_token' => 'rt',
            'expires' => 1349067601 // 2012-10-01 + 1
        ]);

        $this->expectException(IdentityProviderException::class);

        $this->es->refreshAccessToken($token);
    }

    /**
     * @throws IdentityProviderException
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

    public function testRevokeAccessTokenFailure()
    {
        $this->client->setResponse(new Response(400));

        $token = new AccessToken(['access_token' => 'at', 'refresh_token' => 'rt']);

        $result = $this->es->revokeRefreshToken($token);

        $this->assertFalse($result);
        $this->assertStringStartsWith(
            'Error revoking token: 400 Bad Request',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testRevokeAccessToken()
    {
        $this->client->setResponse(new Response(200));

        $token = new AccessToken(['access_token' => 'at', 'refresh_token' => 'rt']);

        $result = $this->es->revokeRefreshToken($token);

        $this->assertTrue($result);
    }

    public function testGetTokenNoExistingTokenException()
    {
        $token = $this->es->getToken(new Character());
        $this->assertSame('', $token);
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
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_grant"}'));

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
            "expires_in": 1200,
            "refresh_token": "gEy...fM0",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->getToken($char);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getAccessToken());
        $this->assertGreaterThan(1519933900, $char->getExpires());
        $this->assertSame('gEy...fM0', $char->getRefreshToken());

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertSame('new-token', $charFromDB->getAccessToken());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
    }

    public function testGetTokenNewTokenUpdateDatabaseError()
    {
        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

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

        $token = $this->es->getToken($c);

        $this->assertSame('', $token);
    }
}
