<?php

declare(strict_types=1);

namespace Tests\ServiceRegistration_AutoloadTest;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    public function getAccounts(CoreCharacter ...$characters): array
    {
        return [];
    }

    public function register(CoreCharacter $character, string $emailAddress): ?ServiceAccountData
    {
        return null;
    }
}
