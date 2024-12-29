<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Entity\App;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\RateLimitApp;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Service\AppAuth;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use Neucore\Util\Crypto;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Helper;
use Tests\Logger;
use Tests\RequestFactory;
use Tests\RequestHandler;

class RateLimitAppTest extends TestCase
{
    private \Doctrine\Persistence\ObjectManager $om;

    private RateLimitApp $middleware;

    private Logger $logger;

    private RepositoryFactory $repoFactory;

    private SystemVariableStorage $storage;

    private int $appId;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();

        $app = (new App())->setName('Test app')->setSecret(password_hash('secret', Crypto::PASSWORD_HASH));
        $maxRequests = (new SystemVariable(SystemVariable::RATE_LIMIT_APP_MAX_REQUESTS))->setValue('50');
        $reset = (new SystemVariable(SystemVariable::RATE_LIMIT_APP_RESET_TIME))->setValue('10');
        $active = (new SystemVariable(SystemVariable::RATE_LIMIT_APP_ACTIVE))->setValue('1');
        $this->om->persist($app);
        $this->om->persist($maxRequests);
        $this->om->persist($reset);
        $this->om->persist($active);
        $this->om->flush();
        $this->appId = $app->getId();

        $this->logger = new Logger();
        $this->repoFactory = new RepositoryFactory($this->om);
        $this->storage = new SystemVariableStorage($this->repoFactory, new ObjectManager($this->om, $this->logger));
        $this->storage->set(
            Variables::RATE_LIMIT_APP . '_' . $this->appId,
            (string) \json_encode((object) ['remaining' => 0, 'created' => time() - 5]),
        );

        $this->middleware = new RateLimitApp(
            new AppAuth($this->repoFactory, $this->om),
            $this->storage,
            new ResponseFactory(),
            $this->logger,
            $this->repoFactory,
        );
    }

    public function testProcess_active()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId . ':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(429, $response->getStatusCode());

        $this->assertSame('-1', $response->getHeader(RateLimit::HEADER_REMAIN)[0]);
        $this->assertEqualsWithDelta(4.5, $response->getHeader(RateLimit::HEADER_RESET)[0], 1.0);
        $this->assertStringStartsWith(
            'Application rate limit exceeded with 51 requests in ', // ... ~5.5 seconds
            $response->getBody()->__toString(),
        );

        $logs = $this->logger->getMessages();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            "API Rate Limit: App $this->appId 'Test app', limit exceeded with 51 request in ", // ... ~5.5 seconds.
            $logs[0],
        );
    }

    public function testProcess_reset()
    {
        $this->storage->set(
            Variables::RATE_LIMIT_APP . '_' . $this->appId,
            (string) \json_encode((object) ['remaining' => 10, 'created' => time() - 15]),
        );

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId . ':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame([
            RateLimit::HEADER_REMAIN => ['49'],
            RateLimit::HEADER_RESET => ['10'],
        ], $response->getHeaders());
    }

    public function testProcess_configured_notActive()
    {
        $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::RATE_LIMIT_APP_ACTIVE)
            ->setValue('0');
        $this->om->flush();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId . ':secret'));

        $response = $this->middleware->process($request, new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());

        $this->assertFalse($response->hasHeader(RateLimit::HEADER_REMAIN));
        $this->assertFalse($response->hasHeader(RateLimit::HEADER_RESET));

        $logs = $this->logger->getMessages();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            "API Rate Limit: App $this->appId 'Test app', limit exceeded with 51 request in ", // ... ~5.5 seconds.
            $logs[0],
        );
    }

    public function testProcess_notConfigured()
    {
        $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::RATE_LIMIT_APP_MAX_REQUESTS)
            ->setValue('');
        $this->om->flush();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId . ':secret'));

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
