<?php

namespace Neucore\Factory;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Neucore\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class SessionHandlerFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws Exception
     */
    public function __invoke(): \SessionHandlerInterface
    {
        // See https://symfony.com/doc/current/session.html#store-sessions-in-a-database
        $pdo = $this->entityManager->getConnection()->getNativeConnection();
        if (!$pdo instanceof \PDO) {
            throw new RuntimeException();
        }
        return new PdoSessionHandler($pdo, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);
    }
}
