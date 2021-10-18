<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Eve\Sso\EveAuthentication;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\EsiTokenRepository;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
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

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var EsiTokenRepository
     */
    private $tokenRepo;

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
        $this->es = new OAuthToken(
            $this->helper->getAuthenticationProvider($this->client),
            new ObjectManager($this->em, $this->log),
            $this->log
        );

        $this->charRepo = (new RepositoryFactory($this->em))->getCharacterRepository();
        $this->tokenRepo = (new RepositoryFactory($this->em))->getEsiTokenRepository();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testRefreshEsiToken_FailCreate()
    {
        $esiToken = new EsiToken();
        $this->assertNull($this->es->refreshEsiToken($esiToken));
    }

    public function testRefreshEsiToken_FailRefresh()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1))->setExpires(1349067601);

        // response for refreshAccessToken() -> IdentityProviderException
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_grant"}'));

        $this->assertNull($this->es->refreshEsiToken($esiToken));

        $this->assertSame('', $esiToken->getAccessToken()); // updated
        $this->assertSame('', $esiToken->getRefreshToken()); // updated
        $this->assertSame(1349067601, $esiToken->getExpires());

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('', $tokenFromDd->getAccessToken()); // updated
    }

    public function testRefreshEsiToken_InvalidData()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1))->setExpires(time() - 60);

        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "new_token", "expires_in": 60, "refresh_token": null}')
        );

        $this->assertNull($this->es->refreshEsiToken($esiToken));

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('at', $tokenFromDd->getAccessToken()); // not updated
    }

    public function testRefreshEsiToken_FailStore()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1))->setExpires(time() - 60);

        $newTokenTime = time() + 60;
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new_token", 
            "refresh_token": "rt2", 
            "expires": '.$newTokenTime.'
        }'));

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $this->assertNull($this->es->refreshEsiToken($esiToken));

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('at', $tokenFromDd->getAccessToken()); // not updated
    }

    public function testRefreshEsiToken_Ok()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1))->setExpires(time() - 60);

        $newTokenTime = time() + 60;
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new_token", 
            "refresh_token": "rt2", 
            "expires": '.$newTokenTime.'
        }'));

        $token = $this->es->refreshEsiToken($esiToken);

        $this->assertInstanceOf(AccessTokenInterface::class, $token);
        $this->assertSame('new_token', $token->getToken());
        $this->assertSame('new_token', $esiToken->getAccessToken());
        $this->assertSame('rt2', $token->getRefreshToken());
        $this->assertSame('rt2', $esiToken->getRefreshToken());
        $this->assertSame($newTokenTime, $token->getExpires());
        $this->assertSame($newTokenTime, $esiToken->getExpires());

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('new_token', $tokenFromDd->getAccessToken());
        $this->assertSame('rt2', $tokenFromDd->getRefreshToken());
        $this->assertSame($newTokenTime, $tokenFromDd->getExpires());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
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

    public function testGetToken_InvalidToken()
    {
        $char = $this->setUpCharacterWithToken('');

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
    }

    public function testUpdateEsiToken_FailRefresh()
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
        $charFromDB = $this->charRepo->find(123);
        $this->assertSame(
            'invalid', // was updated, even if it is possible that it is invalid
            $charFromDB->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken()
        );
    }


    /**
     * @throws \Exception
     */
    public function testUpdateEsiToken_NoScopes()
    {
        $char = $this->setUpCharacterWithToken();
        $esiToken = $this->getToken($char);

        list($token) = Helper::generateToken([]);
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
        $this->assertNull($esiToken->getValidToken());
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
        $this->assertTrue($esiToken->getValidToken());

        $this->em->clear();
        $charFromDB = $this->charRepo->find(123);
        $this->assertNotSame('old-token', $charFromDB->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
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
