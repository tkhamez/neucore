<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Entity\App;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\AppRequestCount;
use Neucore\Service\AppAuth;
use Neucore\Util\Crypto;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\RequestFactory;
use Tests\RequestHandler;

class AppRequestCountTest extends TestCase
{
    private int $appId;

    private RepositoryFactory $repoFactory;

    private AppRequestCount $middleware;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $app = (new App())->setName('Test app')->setSecret(password_hash('secret', Crypto::PASSWORD_HASH));
        $om->persist($app);
        $om->flush();

        $this->appId = $app->getId();
        $this->repoFactory = new RepositoryFactory($om);
        $this->middleware = new AppRequestCount(new AppAuth($this->repoFactory, $om), $this->repoFactory, $om);
    }

    public function testProcess()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId . ':secret'));

        $this->middleware->process($request, new RequestHandler());
        $this->middleware->process($request, new RequestHandler());

        $requests = $this->repoFactory->getAppRequestsRepository()->findBy([]);
        $this->assertSame(1, count($requests));
        $this->assertSame($this->appId, $requests[0]->getApp()->getId());
        $this->assertSame((int) date('Y'), $requests[0]->getYear());
        $this->assertSame((int) date('m'), $requests[0]->getMonth());
        $this->assertSame((int) date('d'), $requests[0]->getDayOfMonth());
        $this->assertSame(2, $requests[0]->getCount());
    }
}
