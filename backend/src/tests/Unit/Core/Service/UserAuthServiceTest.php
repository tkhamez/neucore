<?php
namespace Tests\Unit\Core\Service;

use Tests\Helper;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Entity\UserRepository;
use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;

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
        $this->log->pushHandler(new TestHandler());
        $em = $h->getEm();
        $ur = new UserRepository($em);
        $rr = new RoleRepository($em);

        $this->service = new UserAuthService(new SessionData(), $ur, $rr, $em, $this->log);
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
        $_SESSION['user_id'] = $h->addUser('Test User', 9013, ['user', 'test-role']);

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
        $_SESSION['user_id'] = $h->addUser('Test User', 9013, ['user', 'test-role']);

        $user = $this->service->getUser();

        $this->assertSame($_SESSION['user_id'], $user->getId());
        $this->assertSame(9013, $user->getCharacterId());
    }

    public function testAuthenticateNoUserRoleError()
    {
        (new Helper())->emptyDb();

        $this->assertFalse($this->service->authenticate(888, 'New User'));
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

        $this->assertFalse(isset($_SESSION['user_id']));

        $result = $this->service->authenticate(888, 'New User');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertTrue(isset($_SESSION['user_id']));
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getCharacterId());
        $this->assertSame($_SESSION['user_id'], $user->getId());
        $this->assertSame(['user'], $this->service->getRoles());
    }

    public function testAuthenticateExistingUser()
    {
        $h = new Helper();
        $h->emptyDb();
        (new SessionData())->setReadOnly(false);
        $userId = $h->addUser('Test User', 9013, ['user', 'test-role']);

        $result = $this->service->authenticate(9013, 'Test User Changed Name');
        $user = $this->service->getUser();

        $this->assertTrue($result);
        $this->assertSame($userId, $_SESSION['user_id']);
        $this->assertSame($userId, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame(9013, $user->getCharacterId());
        $this->assertSame(['user', 'test-role'], $this->service->getRoles());
    }
}
