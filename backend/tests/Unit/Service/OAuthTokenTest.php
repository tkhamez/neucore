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
    private static WriteErrorListener $writeErrorListener;

    private Helper $helper;

    private EntityManagerInterface $em;

    private Logger $log;

    private Client $client;

    private OAuthToken $es;

    private CharacterRepository $charRepo;

    private EsiTokenRepository $tokenRepo;

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

        $this->log = new Logger();
        $this->client = new Client();
        $this->es = new OAuthToken(
            Helper::getAuthenticationProvider($this->client),
            new ObjectManager($this->em, $this->log),
            $this->log,
        );

        $this->charRepo = (new RepositoryFactory($this->em))->getCharacterRepository();
        $this->tokenRepo = (new RepositoryFactory($this->em))->getEsiTokenRepository();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testCreateAccessToken(): void
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $esiToken = (new EsiToken())
            ->setEveLogin($eveLogin)
            ->setRefreshToken('refresh')
            ->setAccessToken('access')
            ->setExpires(1519933545);

        $this->assertNull($this->es->createAccessToken(new EsiToken()));

        $accessToken = $this->es->createAccessToken($esiToken);
        $this->assertSame('refresh', $accessToken?->getRefreshToken());
        $this->assertSame('access', $accessToken->getToken());
        $this->assertSame(1519933545 - OAuthToken::EXPIRES_BUFFER, $accessToken->getExpires());
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
            "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->getToken($char, EveLogin::NAME_DEFAULT);

        $this->assertSame('new-token', $token);
        $this->assertSame('new-token', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertGreaterThan(1519933900, $char->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('gEy...fM0', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
    }

    public function testUpdateEsiToken_AlreadyInvalid()
    {
        $char = $this->setUpCharacterWithToken();
        $esiToken = $this->getToken($char);

        $esiToken->setValidToken(false);
        $token1 = $this->es->updateEsiToken($esiToken);
        $this->assertNull($token1);

        $esiToken->setValidToken(true);
        $esiToken->setRefreshToken('');
        $token2 = $this->es->updateEsiToken($esiToken);
        $this->assertNull($token2);
    }

    public function testUpdateEsiToken_RefreshEsiToken_FailCreate()
    {
        $esiToken = new EsiToken();
        $esiToken->setValidToken(true);
        $esiToken->setRefreshToken('rt');
        $this->assertNull($this->es->updateEsiToken($esiToken));
        $this->assertNull($esiToken->getLastChecked());
    }

    public function testUpdateEsiToken_RefreshEsiToken_FailRefresh(): void
    {
        $expires = 1349067701; // Oct 01 2012 05:01:41
        $esiToken = $this->getToken(
            $this->helper->addCharacterMain('Name', 1, tokenExpires: $expires, tokenValid: true)
        );
        $this->assertNull($esiToken->getLastChecked());

        // response for refreshAccessToken() -> IdentityProviderException
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_grant"}'));

        $this->assertNull($this->es->updateEsiToken($esiToken));

        $this->assertSame('', $esiToken->getAccessToken()); // updated
        $this->assertSame('rt', $esiToken->getRefreshToken()); // not updated
        $this->assertNotNull($esiToken->getLastChecked()); // updated
        $this->assertSame($expires, $esiToken->getExpires());

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('', $tokenFromDd?->getAccessToken()); // updated
    }

    public function testUpdateEsiToken_RefreshEsiToken_InvalidData()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1, [], [], true, null, time() - 60, true));
        $this->assertNull($esiToken->getLastChecked());

        $this->client->setResponse(
            new Response(
                200,
                [],
                '{"access_token": "new_token", "expires_in": 60, "refresh_token": null}',
            ),
        );

        $this->assertNull($this->es->updateEsiToken($esiToken));
        $this->assertNull($esiToken->getLastChecked());

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('at', $tokenFromDd->getAccessToken()); // not updated
    }

    public function testUpdateEsiToken_RefreshEsiToken_FailStore()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1, [], [], true, null, time() - 60, true));

        $newTokenTime = time() + 60;
        $this->client->setResponse(new Response(200, [], '{
            "access_token": "new_token", 
            "refresh_token": "rt2", 
            "expires": ' . $newTokenTime . '
        }'));

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $this->assertNull($this->es->updateEsiToken($esiToken));
        $this->assertNotNull($esiToken->getLastChecked()); // updated, but not stored

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('at', $tokenFromDd->getAccessToken()); // not updated
        $this->assertNull($tokenFromDd->getLastChecked()); // updated, but not stored
    }

    public function testUpdateEsiToken_RefreshEsiToken_Ok_InvalidToken()
    {
        $esiToken = $this->getToken($this->helper->addCharacterMain('Name', 1, [], [], true, null, time() - 60, true));
        $this->assertNull($esiToken->getLastChecked());

        $newTokenTime = time() + 60;
        $this->client->setResponse(new Response(
            200,
            [],
            '{"access_token": "invalid",
            "refresh_token": "updated",
            "expires": ' . $newTokenTime . '}', // 03/01/2018 @ 7:51pm (UTC)
        ));

        $token = $this->es->updateEsiToken($esiToken);

        $this->assertInstanceOf(AccessTokenInterface::class, $token);
        $this->assertSame('invalid', $token->getToken());
        $this->assertSame('invalid', $esiToken->getAccessToken());
        $this->assertSame('updated', $token->getRefreshToken());
        $this->assertSame('updated', $esiToken->getRefreshToken());
        $this->assertSame($newTokenTime, $token->getExpires());
        $this->assertSame($newTokenTime, $esiToken->getExpires());
        $this->assertLessThanOrEqual(time(), $esiToken->getLastChecked()->getTimestamp());

        $this->em->clear();
        $tokenFromDd = $this->tokenRepo->find($esiToken->getId());
        $this->assertSame('invalid', $tokenFromDd->getAccessToken());
        $this->assertSame('updated', $tokenFromDd->getRefreshToken());
        $this->assertSame($newTokenTime, $tokenFromDd->getExpires());
        $this->assertLessThanOrEqual(time(), $tokenFromDd->getLastChecked()->getTimestamp());

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
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
            "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
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
            "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
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
            ->setValidToken(true)->setExpires(1519933545); // 03/01/2018 @ 7:45pm (UTC)
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
        return $char->getEsiToken(EveLogin::NAME_DEFAULT);
    }
}
