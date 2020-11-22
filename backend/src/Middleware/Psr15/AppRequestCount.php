<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\AppRequests;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AppRequestCount implements MiddlewareInterface
{
    /**
     * @var AppAuth
     */
    private $appAuth;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $om;

    public function __construct(
        AppAuth $appAuth,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager
    ) {
        $this->appAuth = $appAuth;
        $this->repositoryFactory = $repositoryFactory;
        $this->om = $objectManager;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $app = $this->appAuth->getApp($request);
        if ($app === null) {
            // Not a request from an authorized app.
            return $handler->handle($request);
        }

        $day = date('Y-m-d');

        $requests = $this->repositoryFactory->getAppRequestsRepository()->findOneBy(['app' => $app, 'day' => $day]);
        if ($requests === null) {
            $requests = new AppRequests();
            $requests->setApp($app);
            $requests->setDay($day);
            $requests->setCount(0);
            $this->om->persist($requests);
        }

        $requests->setCount($requests->getCount() + 1);
        $this->om->flush();

        return $handler->handle($request);
    }
}
