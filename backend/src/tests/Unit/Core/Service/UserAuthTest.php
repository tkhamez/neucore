<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Roles;
use Brave\Core\Service\CoreCharacter;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Brave\Slim\Session\SessionData;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;

class UserAuthTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Logger
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $service;

    public function setUp()
    {
        $h = new Helper();

        $h->resetSessionData();
        $_SESSION = []; // "start" session for SessionData object and reset data

        $this->log = new Logger('test');
        $em = $h->getEm();

        $oauth = $this->createMock(GenericProvider::class); /* @var $oauth GenericProvider */
        $token = new OAuthToken($oauth, new ObjectManager($em, $this->log), $this->log);
        $coreCharacter = new CoreCharacter($this->log, new ObjectManager($em, $this->log), $token);
        $this->service = new UserAuth(new SessionData(), $coreCharacter, new RepositoryFactory($em), $this->log);
    }

    public function testGetRolesNoAuth()
    {
        $roles = $this->service->getRoles();
        $this->assertSame([], $roles);
    }

    public function testGetRoles()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Test User', 9013, [Roles::USER, Roles::GROUP_MANAGER]);
        $_SESSION['character_id'] = 9013;

        $roles = $this->service->getRoles();

        $this->assertSame([Roles::USER, Roles::GROUP_MANAGER], $roles);
    }

    public function testGetUserNoAuth()
    {
        $user = $this->service->getUser();
        $this->assertNull($user);
    }

    public function testGetUser()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Test User', 9013, [Roles::USER, Roles::GROUP_MANAGER]);
        $_SESSION['character_id'] = 9013;

        $user = $this->service->getUser();

        $this->assertSame(9013, $user->getId());
    }

    public function testAuthenticateNoUserRoleError()
    {
        (new Helper())->emptyDb();
        $this->log->pushHandler(new TestHandler());

        $token = new AccessToken(['access_token' => 'token']);
        $this->assertFalse($this->service->authenticate(888, 'New User', 'char-owner-hash', '', $token));
        $this->assertSame(
            'UserAuth::authenticate(): Role "'.Roles::USER.'" not found.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testAuthenticateNewUser()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles([Roles::USER]);
        (new SessionData())->setReadOnly(false);

        $this->assertFalse(isset($_SESSION['character_id']));

        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(888, 'New User', 'coh', 'scope1 s2', $token);
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertTrue($user->getMain());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($user->getValidToken());
        $this->assertSame('scope1 s2', $user->getScopes());
        $this->assertSame($_SESSION['character_id'], $user->getId());
        $this->assertSame([Roles::USER], $this->service->getRoles());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertTrue((new \DateTime())->diff($user->getLastLogin())->format('%s') < 2);
    }

    public function testAuthenticateExistingUser()
    {
        $h = new Helper();
        $h->emptyDb();
        (new SessionData())->setReadOnly(false);
        $char = $h->addCharacterMain('Test User', 9013, [Roles::USER, Roles::GROUP_MANAGER]);

        $this->assertSame('123', $char->getCharacterOwnerHash());
        $this->assertSame('abc', $char->getAccessToken());
        $this->assertSame(123456, $char->getExpires());
        $this->assertSame('def', $char->getRefreshToken());
        $this->assertFalse($char->getValidToken());

        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(9013, 'Test User Changed Name', 'coh', 'scope1 s2', $token);
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame('scope1 s2', $user->getScopes());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($char->getValidToken());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertTrue((new \DateTime())->diff($user->getLastLogin())->format('%s') < 2);
    }

    public function testAddAlt()
    {
        $h = new Helper();
        $h->emptyDb();
        $_SESSION['character_id'] = 100;
        $main = $h->addCharacterMain('Main', 100, [Roles::USER]);
        $player = $main->getPlayer();

        $this->assertSame(1, count($player->getCharacters()));

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785, 'refresh_token' => 'rf']);
        $result = $this->service->addAlt(101, 'Alt 1', 'hash', 'scope1 s2', $token);
        $this->assertTrue($result);

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame(101, $chars[1]->getId());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
        $this->assertSame(1525456785, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
        $this->assertTrue($chars[1]->getValidToken());
        $this->assertFalse($chars[1]->getMain());
    }

    public function testAddAltExistingChar()
    {
        $h = new Helper();
        $h->emptyDb();
        $_SESSION['character_id'] = 100;
        $main1 = $h->addCharacterMain('Main1', 100, [Roles::USER]);
        $main2 = $h->addCharacterMain('Main2', 200, [Roles::USER]);

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785, 'refresh_token' => 'rf']);
        $result = $this->service->addAlt(200, 'Main2 renamed', 'hash', 'scope1 s2', $token);
        $this->assertTrue($result);

        $chars = $main1->getPlayer()->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame($main2, $chars[1]);
        $this->assertSame('Main2 renamed', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
        $this->assertSame(1525456785, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
        $this->assertTrue($chars[1]->getValidToken());
    }

    public function testAddAltLoggedInChar()
    {
        $h = new Helper();
        $h->emptyDb();
        $_SESSION['character_id'] = 100;
        $main = $h->addCharacterMain('Main1', 100, [Roles::USER]);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->addAlt(100, 'Main1 renamed', 'hash', '', $token);
        $this->assertTrue($result);

        $chars = $main->getPlayer()->getCharacters();
        $this->assertSame(1, count($chars));
        $this->assertSame('Main1', $chars[0]->getName()); // not changed
    }

    public function testAddAltNotAuthenticated()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Main1', 100, [Roles::USER]);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->addAlt(100, 'Main1 renamed', 'hash', '', $token);
        $this->assertFalse($result);
    }
}
