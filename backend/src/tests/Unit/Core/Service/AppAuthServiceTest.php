<?php
namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\AppRepository;
use Brave\Core\Service\AppAuthService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Environment;
use Slim\Http\Request;
use Tests\Helper;

class AppAuthServiceTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var AppAuthService
     */
    private $service;

    /**
     * @var AppAuthService
     */
    private $repo;

    public function setUp()
    {
        $log = new Logger('test');
        $log->pushHandler(new StreamHandler('php://stderr'));
        $em = (new Helper())->getEm();
        $this->repo = new AppRepository($em);

        $this->service = new AppAuthService($this->repo, $em, $log);
    }

    public function testGetRolesNoAuth()
    {
        $req = Request::createFromEnvironment(Environment::mock());
        $roles = $this->service->getRoles($req);

        $this->assertSame([], $roles);
    }

    public function testGetRoles()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app']);

        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

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
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode('1:invalid-secret')]);

        $this->assertNull($this->service->getApp($req));
    }

    public function testGetApp()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app']);

        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

        $app = $this->service->getApp($req);

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppAuthenticateUpgradesPasswordHash()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'], 'md5');

        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('hasHeader')->willReturn(true);
        $req->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

        $oldHash = $this->repo->find($appId)->getSecret();
        $newHash = $this->service->getApp($req)->getSecret();

        $this->assertStringStartsWith('$1$', $oldHash);
        $this->assertStringStartsNotWith('$1$', $newHash);
    }
}
