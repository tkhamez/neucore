<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Entity\App;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Service\AppAuth;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Helper;
use Tests\Logger;
use Tests\RequestFactory;
use Tests\RequestHandler;

class RateLimitTest extends TestCase
{
    /**
     * @var \Doctrine\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var RateLimit
     */
    private $middleware;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    /**
     * @var SystemVariableStorage
     */
    private $storage;

    /**
     * @var int
     */
    private $appId;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();

        $app = (new App())->setName('Test app')->setSecret((string) password_hash('secret', PASSWORD_BCRYPT));
        $maxRequests = (new SystemVariable(SystemVariable::API_RATE_LIMIT_MAX_REQUESTS))->setValue('50');
        $reset = (new SystemVariable(SystemVariable::API_RATE_LIMIT_RESET_TIME))->setValue('10');
        $active = (new SystemVariable(SystemVariable::API_RATE_LIMIT_ACTIVE))->setValue('1');
        $this->om->persist($app);
        $this->om->persist($maxRequests);
        $this->om->persist($reset);
        $this->om->persist($active);
        $this->om->flush();
        $this->appId = $app->getId();

        $this->logger = new Logger('Test');
        $this->repoFactory = new RepositoryFactory($this->om);
        $this->storage = new SystemVariableStorage($this->repoFactory, new ObjectManager($this->om, $this->logger));
        $this->storage->set(
            Variables::API_RATE_LIMIT . '_' . $this->appId,
            (string) \json_encode((object) ['remaining' => '0', 'created' => time() - 5])
        );

        $this->middleware = new RateLimit(
            new AppAuth($this->repoFactory, $this->om),
            $this->storage,
            new ResponseFactory(),
            $this->logger,
            $this->repoFactory
        );
    }

    public function testProcess_active()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId.':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(429, $response->getStatusCode());

        $this->assertSame('-1', $response->getHeader(RateLimit::HEADER_REMAIN)[0]);
        $this->assertEqualsWithDelta(4.5, $response->getHeader(RateLimit::HEADER_RESET)[0], 1.0);

        $logs = $this->logger->getHandler()->getRecords();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            "API Rate Limit: App {$this->appId} 'Test app', limit exceeded with 51 request in ", // ... ~5.5 seconds.
            $logs[0]['message']
        );
    }

    public function testProcess_reset()
    {
        $this->storage->set(
            Variables::API_RATE_LIMIT . '_' . $this->appId,
            (string) \json_encode((object) ['remaining' => '10', 'created' => time() - 15])
        );

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId.':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([
            RateLimit::HEADER_REMAIN => ['49'],
            RateLimit::HEADER_RESET => ['10.0'],
        ], $response->getHeaders());

        $logs = $this->logger->getHandler()->getRecords();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            "API Rate Limit: App {$this->appId} 'Test app', 41 requests in ", // ... ~15.5 seconds.
            $logs[0]['message']
        );
    }

    public function testProcess_configured_notActive()
    {
        ($this->repoFactory->getSystemVariableRepository()->find(SystemVariable::API_RATE_LIMIT_ACTIVE))
            ->setValue('0');
        $this->om->flush();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId.':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame('-1', $response->getHeader(RateLimit::HEADER_REMAIN)[0]);
        $this->assertEqualsWithDelta(4.5, $response->getHeader(RateLimit::HEADER_RESET)[0], 1.0);
    }

    public function testProcess_notConfigured()
    {
        ($this->repoFactory->getSystemVariableRepository()->find(SystemVariable::API_RATE_LIMIT_MAX_REQUESTS))
            ->setValue('');
        $this->om->flush();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId.':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProcess_noApp()
    {
        $request = RequestFactory::createRequest();

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
