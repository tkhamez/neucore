<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Eve\Sso\EveAuthentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
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

    public function testCreateAccessToken()
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $esiToken = (new EsiToken())
            ->setEveLogin($eveLogin)
            ->setRefreshToken('refresh')
            ->setAccessToken('access')
            ->setExpires(1519933545);

        $this->assertNull($this->es->createAccessToken(new EsiToken()));

        $accessToken = $this->es->createAccessToken($esiToken);
        $this->assertSame('refresh', $accessToken->getRefreshToken());
        $this->assertSame('access', $accessToken->getToken());
        $this->assertSame(1519933545, $accessToken->getExpires());
    }

    /**
     * @throws \Exception
     */
    public function testGetScopesFromToken()
    {
        $esiToken = (new EsiToken())->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT));

        // invalid token error
        $this->assertSame([], $this->es->getScopesFromToken($esiToken));

        // UnexpectedValueException
        $esiToken->setAccessToken('invalid');
        $this->assertSame([], $this->es->getScopesFromToken($esiToken));

        // valid token
        $token = Helper::generateToken(['s1', 's2']);
        $esiToken->setAccessToken($token[0]);
        $this->assertSame(['s1', 's2'], $this->es->getScopesFromToken($esiToken));
    }

    public function testGetToken_NoToken()
    {
        $token = $this->es->getToken(new Character(), 'test-1');
        $this->assertSame('', $token);
    }

    public function testGetToken_InvalidToken1()
    {
        $char = $this->setUpCharacterWithToken('');

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('', $token);
    }

    public function testGetToken_InvalidToken2()
    {
        $char = $this->setUpCharacterWithToken();

        // response for refreshAccessToken()
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_grant"}'));

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('', $token);
    }

    public function testGetToken_InvalidToken3()
    {
        $char = $this->setUpCharacterWithToken();

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "rt", "expires_in": 0, "refresh_token": "", "expires": 1519933900}'
        ));

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('', $token);
    }

    public function testGetToken_NewTokenUpdateDatabaseOk()
    {
        $char = $this->setUpCharacterWithToken();

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new-token",
            "expires_in": 1200,
            "refresh_token": "gEy...fM0",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertGreaterThan(1519933900, $char->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('gEy...fM0', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertSame('new-token', $charFromDB->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
    }

    public function testGetToken_NewTokenUpdateDatabaseError()
    {
        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $esiToken = (new EsiToken())->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT))
            ->setAccessToken('old-token')->setRefreshToken('r')
            ->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $char = (new Character())->addEsiToken($esiToken);
        $esiToken->setCharacter($char);

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new-token",
            "refresh_token": "r",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('', $token);
    }

    public function testUpdateEsiToken_InvalidGrant()
    {
        $char = $this->setUpCharacterWithToken();
        $esiToken = $this->getToken($char);

        $this->client->setResponse(new Response(400, [], '{ "error": "invalid_grant" }'));

        $token = $this->es->updateEsiToken($esiToken);

        $this->assertNull($token);
        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
    }

    public function testUpdateEsiToken_InvalidToken()
    {
        $char = $this->setUpCharacterWithToken();
        $esiToken = $this->getToken($char);

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "invalid",
            "expires_in": 1200,
            "refresh_token": "gEy...fM0",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->updateEsiToken($esiToken);

        $this->assertInstanceOf(AccessTokenInterface::class, $token);
        $this->assertSame('invalid', $token->getToken()); // new token with data from response

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertSame(
            'old-token', // was not updated
            $charFromDB->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken()
        );
    }

    /**
     * @throws \Exception
     */
    public function testUpdateEsiToken_Success()
    {
        $char = $this->setUpCharacterWithToken();
        $esiToken = $this->getToken($char);

        list($token) = Helper::generateToken();
        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": ' . json_encode($token) . ',
            "expires_in": 1200,
            "refresh_token": "gEy...fM0",
            "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->updateEsiToken($esiToken);

        $this->assertInstanceOf(AccessTokenInterface::class, $token);
        $this->assertNotSame('new-token', $token->getToken());
        $this->assertGreaterThan(1519933900, $token->getExpires());
        $this->assertSame('gEy...fM0', $token->getRefreshToken());

        $this->em->clear();
        $charFromDB = (new RepositoryFactory($this->em))->getCharacterRepository()->find(123);
        $this->assertNotSame('new-token', $charFromDB->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
    }

    /**
     * @throws \Exception
     */
    public function testGetEveAuth()
    {
        $this->assertNull($this->es->getEveAuth(new AccessToken(['access_token' => 'invalid'])));

        list($token) = Helper::generateToken(['scope1']);
        $result = $this->es->getEveAuth(new AccessToken(['access_token' => $token]));
        $this->assertInstanceOf(EveAuthentication::class, $result);
        $this->assertSame(['scope1'], $result->getScopes());
    }

    private function setUpCharacterWithToken(string $accessToken = 'old-token'): Character
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setAccessToken($accessToken)->setRefreshToken('r')
            ->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
        $char = (new Character())->setId(123)->setName('n')->setMain(true)->setCharacterOwnerHash('coh')
            ->addEsiToken($esiToken);
        $esiToken->setCharacter($char);
        $this->helper->getEm()->persist($eveLogin);
        $this->helper->getEm()->persist($esiToken);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        return $char;
    }

    private function getToken(Character $char): EsiToken
    {
        /* @phan-suppress-next-line PhanTypeMismatchReturnNullable */
        return $char->getEsiToken(EveLogin::NAME_DEFAULT);
    }
}
