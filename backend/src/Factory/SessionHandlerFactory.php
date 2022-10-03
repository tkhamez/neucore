<?php

namespace Neucore\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SessionHandlerFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(): \SessionHandlerInterface
    {
        // see https://symfony.com/doc/current/components/http_foundation/session_configuration.html
        $pdo = $this->entityManager->getConnection()->getNativeConnection(); /* @var \PDO $pdo */
        /** @psalm-suppress InvalidArgument */
        return new PdoSessionHandler($pdo, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);
    }
}
