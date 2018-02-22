<?php
namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\AppRepository;
use Brave\Core\Service\AppAuthService;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Environment;
use Slim\Http\Request;
use Tests\Helper;

class AppAuthServiceTest extends \PHPUnit\Framework\TestCase
{

    private static $appId;

    /**
     * @var AppAuthService
     */
    private $service;

    public static function setUpBeforeClass()
    {
        $h = new Helper();
        $h->emptyDb();
        self::$appId = $h->addApp('Test App', 'my-test-secret', ['app']);
    }

    public function setUp()
    {
        $log = new Logger('test');
        $em = (new Helper())->getEm();
        $repo = new AppRepository($em);

        $this->service = new AppAuthService($repo, $em, $log);
    }

    public function testGetRolesNoAuth()
    {
        $req = Request::createFromEnvironment(Environment::mock());
        $roles = $this->service->getRoles($req);

        $this->assertSame([], $roles);
    }

    public function testGetRoles()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode(self::$appId.':my-test-secret')]);

        $roles = $this->service->getRoles($req);

        $this->assertSame(['app'], $roles);
    }

    public function testGetAppNoAuth()
    {
        $req = Request::createFromEnvironment(Environment::mock());
        $app = $this->service->getApp($req);

        $this->assertNull($app);
    }

    public function testGetAppBrokenAuth()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);

        $req->method('getHeader')->willReturn(['Bearer not:b64-encoded']);
        $this->assertNull($this->service->getApp($req));
    }

    public function testGetAppBrokenAuth2()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);

        $req->method('getHeader')->willReturn(['Bearer '.base64_encode('no-id')]);
        $this->assertNull($this->service->getApp($req));
    }

    public function testGetAppInvalidPass()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode(self::$appId.':invalid-secret')]);

        $this->assertNull($this->service->getApp($req));
    }

    public function testGetApp()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode(self::$appId.':my-test-secret')]);

        $app = $this->service->getApp($req);

        $this->assertSame(self::$appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }
}
