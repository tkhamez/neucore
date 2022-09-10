<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Repository\AppRepository;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Helper;
use Tests\RequestFactory;

class AppAuthTest extends TestCase
{
    /**
     * @var AppAuth
     */
    private $service;

    /**
     * @var AppRepository
     */
    private $repo;

    protected function setUp(): void
    {
        $helper = new Helper();
        $repositoryFactory = new RepositoryFactory($helper->getObjectManager());
        $this->repo = $repositoryFactory->getAppRepository();

        $this->service = new AppAuth($repositoryFactory, $helper->getObjectManager());
    }

    public function testGetRolesNoAuth()
    {
        $roles = $this->service->getRoles($this->getRequest());

        $this->assertSame([], $roles);
    }

    public function testGetRoles()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer '.base64_encode($appId.':my-test-secret');

        $roles = $this->service->getRoles($this->getRequest($header));

        $this->assertSame(['app'], $roles);
    }

    public function testGetAppNoAuth()
    {
        $req = RequestFactory::createRequest();
        $app = $this->service->getApp($req);

        $this->assertNull($app);
    }

    public function testGetAppBrokenAuth()
    {
        $header = 'Bearer not:b64-encoded';

        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppBrokenAuth2()
    {
        $header = 'Bearer '.base64_encode('no-id');

        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppInvalidPass()
    {
        $header = 'Bearer '.base64_encode('1:invalid-secret');

        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetApp()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer '.base64_encode($appId.':my-test-secret');

        $app = $this->service->getApp($this->getRequest($header));

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppAuthenticateUpgradesPasswordHash()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'], null, 'md5')->getId();

        $header = 'Bearer '.base64_encode($appId.':my-test-secret');

        $oldHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsWith('$1$', $oldHash);

        $this->service->getApp($this->getRequest($header));
        $h->getObjectManager()->clear();

        $newHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsNotWith('$1$', $newHash);
    }

    private function getRequest(string $authHeader = null): ServerRequestInterface
    {
        $request = RequestFactory::createRequest();
        if ($authHeader !== null) {
            $request = $request->withHeader('Authorization', $authHeader);
        }
        return $request;
    }
}
