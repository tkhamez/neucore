<?php
namespace Tests\Unit\Core\Service;

use Brave\Core\Service\UserAuthService;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
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
        $_SESSION = []; // "start" session for SessionData object

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
        $h->addCharacterMain('Test User', 9013, ['user', 'test-role']);
        $_SESSION['character_id'] = 9013;

        $roles = $this->service->getRoles();

        $this->assertSame(['user', 'test-role'], $roles);
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
        $h->addCharacterMain('Test User', 9013, ['user', 'test-role']);
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
            'UserAuthService::authenticate(): Role "user" not found.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testAuthenticateNewUser()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addRoles(['user']);
        (new SessionData())->setReadOnly(false);

        $this->assertFalse(isset($_SESSION['character_id']));

        $result = $this->service->authenticate(888, 'New User', 'coh', 'token', 123456, 'refresh');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame(123456, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertSame($_SESSION['character_id'], $user->getId());
        $this->assertSame(['user'], $this->service->getRoles());
    }

    public function testAuthenticateExistingUser()
    {
        $h = new Helper();
        $h->emptyDb();
        (new SessionData())->setReadOnly(false);
        $char = $h->addCharacterMain('Test User', 9013, ['user', 'test-role']);

        $this->assertSame('123', $char->getCharacterOwnerHash());
        $this->assertSame('abc', $char->getAccessToken());
        $this->assertSame(123456, $char->getExpires());
        $this->assertSame('def', $char->getRefreshToken());

        $result = $this->service->authenticate(9013, 'Test User Changed Name', 'coh', 'token', 456, 'refresh');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame(456, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
    }

    public function testUpdateAccessTokenNoUser()
    {
        $this->log->popHandler();
        $this->log->pushHandler(new TestHandler());

        $this->service->updateAccessToken('new-token', 456789);

        $this->assertSame(
            'UserAuthService::updateAccessToken(): User not found.',
            $this->log->getHandlers()[0]->getRecords()[0]['message']
        );
    }

    public function testUpdateAccessToken()
    {
        $h = new Helper();
        $h->emptyDb();
        (new SessionData())->setReadOnly(false);
        $this->service->authenticate(9013, 'Test User Changed Name', 'coh', 'token');

        $char = $this->service->getUser();

        $oldToken = $char->getAccessToken();
        $oldExpires = $char->getExpires();

        $this->service->updateAccessToken('new-token', 456789);

        $this->assertNotSame($char->getAccessToken(), $oldToken);
        $this->assertNotSame($char->getExpires(), $oldExpires);
    }
}
