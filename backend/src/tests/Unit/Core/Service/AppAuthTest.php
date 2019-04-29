<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Repository\AppRepository;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\ObjectManager;
use Monolog\Logger;
use Slim\Http\Cookies;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Uri;
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

    public function setUp()
    {
        $log = new Logger('test');
        $em = (new Helper())->getEm();
        $repositoryFactory = new RepositoryFactory($em);
        $this->repo = $repositoryFactory->getAppRepository();

        $this->service = new AppAuth($repositoryFactory, new ObjectManager($em, $log));
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
        $req = Request::createFromEnvironment(Environment::mock());
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
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'], 'md5')->getId();

        $header = 'Bearer '.base64_encode($appId.':my-test-secret');

        $oldHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsWith('$1$', $oldHash);

        $this->service->getApp($this->getRequest($header));
        $h->getEm()->clear();

        $newHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsNotWith('$1$', $newHash);
    }

    private function getRequest(string $authHeader = null)
    {
        $environment = Environment::mock();

        $method = $environment['REQUEST_METHOD'];
        $uri = Uri::createFromEnvironment($environment);
        $headers = Headers::createFromEnvironment($environment);
        $cookies = Cookies::parseHeader($headers->get('Cookie', [''])[0]);
        $serverParams = $environment->all();
        $body = new RequestBody();

        $request = new class($method, $uri, $headers, $cookies, $serverParams, $body) extends Request {
            private $fakeHeaders = [];

            public function hasHeader($name)
            {
                return isset($this->fakeHeaders[$name]);
            }

            public function setAuthHeader(string $authHeader)
            {
                $this->fakeHeaders['Authorization'] = [$authHeader];
            }

            public function getHeader($name)
            {
                return $this->fakeHeaders[$name];
            }
        };

        if ($authHeader !== null) {
            $request->setAuthHeader($authHeader);
        }

        return $request;
    }
}
