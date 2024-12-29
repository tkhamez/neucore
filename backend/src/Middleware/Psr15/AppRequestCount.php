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
    private AppAuth $appAuth;

    private RepositoryFactory $repositoryFactory;

    private ObjectManager $om;

    public function __construct(
        AppAuth $appAuth,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
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

        $year = (int) date('Y');
        $month = (int) date('m');
        $day = (int) date('d');
        $hour = (int) date('G');

        $requests = $this->repositoryFactory->getAppRequestsRepository()->findOneBy([
            'app' => $app,
            'year' => $year,
            'month' => $month,
            'dayOfMonth' => $day,
            'hour' => $hour,
        ]);
        if ($requests === null) {
            $requests = new AppRequests();
            $requests->setApp($app);
            $requests->setYear($year);
            $requests->setMonth($month);
            $requests->setDayOfMonth($day);
            $requests->setHour($hour);
            $requests->setCount(0);
            $this->om->persist($requests);
        }

        $requests->setCount($requests->getCount() + 1);
        $this->om->flush();

        return $handler->handle($request);
    }
}
