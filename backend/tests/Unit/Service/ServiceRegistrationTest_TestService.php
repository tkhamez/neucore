<?php
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistrationTest_TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    public function getAccounts(CoreCharacter ...$characters): array
    {
        return [
            new ServiceAccountData($characters[0]->id, 'u', 'p', 'e'),
            [],
            new ServiceAccountData(123456),
        ];
    }

    public function register(CoreCharacter $character, string $emailAddress): ?ServiceAccountData
    {
        return null;
    }
}
