<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;

class UserAuthServiceTest extends \PHPUnit\Framework\TestCase
{
    private $log;

    /**
     * @var UserAuthService
     */
    private $service;

    public static function setUpBeforeClass()
    {
    }

    public function setUp()
    {
        $h = new Helper();

        $h->resetSessionData();
        $_SESSION = []; // "start" session for SessionData object and reset data

        $this->log = new Logger('test');
        $this->log->pushHandler(new StreamHandler('php://stderr'));
        $em = $h->getEm();
        $cr = new CharacterRepository($em);
        $rr = new RoleRepository($em);

        $this->service = new UserAuthService(new SessionData(), $cr, $rr, $em, $this->log);
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
        $this->log->popHandler();
        $this->log->pushHandler(new TestHandler());

        $this->assertFalse($this->service->authenticate(888, 'New User', 'char-owner-hash', 'token'));
        $this->assertSame(
            'UserAuthService::authenticate(): Role "'.Roles::USER.'" not found.',
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

        $result = $this->service->authenticate(888, 'New User', 'coh', 'token', 'scope1 s2', 123456, 'refresh');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertTrue($user->getMain());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame(123456, $user->getExpires());
        $this->assertSame('token', $user->getAccessToken());
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

        $result = $this->service->authenticate(
            9013, 'Test User Changed Name', 'coh', 'token', 'scope1 s2', 456, 'refresh');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame('scope1 s2', $user->getScopes());
        $this->assertSame(456, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
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

        $result = $this->service->addAlt(101, 'Alt 1', 'hash', 'tk', 'scope1 s2', 123456789, 'rf');
        $this->assertTrue($result);

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame(101, $chars[1]->getId());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
        $this->assertSame(123456789, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
        $this->assertFalse($chars[1]->getMain());
    }

    public function testAddAltExistingChar()
    {
        $h = new Helper();
        $h->emptyDb();
        $_SESSION['character_id'] = 100;
        $main1 = $h->addCharacterMain('Main1', 100, [Roles::USER]);
        $main2 = $h->addCharacterMain('Main2', 200, [Roles::USER]);

        $result = $this->service->addAlt(200, 'Main2 renamed', 'hash', 'tk', 'scope1 s2', 123456789, 'rf');
        $this->assertTrue($result);

        $chars = $main1->getPlayer()->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame($main2, $chars[1]);
        $this->assertSame('Main2 renamed', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
        $this->assertSame(123456789, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
    }

    public function testAddAltLoggedInChar()
    {
        $h = new Helper();
        $h->emptyDb();
        $_SESSION['character_id'] = 100;
        $main = $h->addCharacterMain('Main1', 100, [Roles::USER]);

        $result = $this->service->addAlt(100, 'Main1 renamed', 'hash', 'tk');
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

        $result = $this->service->addAlt(100, 'Main1 renamed', 'hash', 'tk');
        $this->assertFalse($result);
    }
}
