<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Entity\App;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\AppRequestCount;
use Neucore\Service\AppAuth;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\RequestFactory;
use Tests\RequestHandler;

class AppRequestCountTest extends TestCase
{
    /**
     * @var int
     */
    private $appId;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    /**
     * @var AppRequestCount
     */
    private $middleware;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $app = (new App())->setName('Test app')->setSecret((string) password_hash('secret', PASSWORD_BCRYPT));
        $om->persist($app);
        $om->flush();

        $this->appId = $app->getId();
        $this->repoFactory = new RepositoryFactory($om);
        $this->middleware = new AppRequestCount(new AppAuth($this->repoFactory, $om), $this->repoFactory, $om);
    }

    public function testProcess()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Authorization', 'Bearer ' . base64_encode($this->appId.':secret'));

        $this->middleware->process($request, new RequestHandler());
        $this->middleware->process($request, new RequestHandler());

        $requests = $this->repoFactory->getAppRequestsRepository()->findBy([]);
        $this->assertSame(1, count($requests));
        $this->assertSame($this->appId, $requests[0]->getApp()->getId());
        $this->assertSame(date('Y-m-d'), $requests[0]->getDay());
        $this->assertSame(2, $requests[0]->getCount());
    }
}
