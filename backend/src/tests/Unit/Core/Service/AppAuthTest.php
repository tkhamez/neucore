<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Repository\AppRepository;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\ObjectManager;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Environment;
use Slim\Http\Request;
use Tests\Helper;

class AppAuthTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var AppAuth
     */
    private $service;

    /**
     * @var AppRepository
     */
    private $repo;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ServerRequestInterface
     */
    private $request;

    public function setUp()
    {
        $log = new Logger('test');
        $em = (new Helper())->getEm();
        $repositoryFactory = new RepositoryFactory($em);
        $this->repo = $repositoryFactory->getAppRepository();

        $this->service = new AppAuth($repositoryFactory, new ObjectManager($em, $log));
        $this->request = $this->createMock(ServerRequestInterface::class);
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
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

        $roles = $this->service->getRoles($this->request);

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
        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer not:b64-encoded']);

        $this->assertNull($this->service->getApp($this->request));
    }

    public function testGetAppBrokenAuth2()
    {
        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer '.base64_encode('no-id')]);

        $this->assertNull($this->service->getApp($this->request));
    }

    public function testGetAppInvalidPass()
    {
        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer '.base64_encode('1:invalid-secret')]);

        $this->assertNull($this->service->getApp($this->request));
    }

    public function testGetApp()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

        $app = $this->service->getApp($this->request);

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppAuthenticateUpgradesPasswordHash()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'], 'md5')->getId();

        $this->request->method('hasHeader')->willReturn(true);
        $this->request->method('getHeader')->willReturn(['Bearer '.base64_encode($appId.':my-test-secret')]);

        $oldHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsWith('$1$', $oldHash);

        $this->service->getApp($this->request);
        $h->getEm()->clear();

        $newHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsNotWith('$1$', $newHash);
    }
}
