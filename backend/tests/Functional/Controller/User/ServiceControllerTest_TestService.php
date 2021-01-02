<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceControllerTest_TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    public function getAccounts(CoreCharacter ...$characters): array
    {
        return [
            new ServiceAccountData(1, 'u', 'p', 'e', ServiceAccountData::STATUS_ACTIVE),
        ];
    }

    public function register(CoreCharacter $character, string $emailAddress): ?ServiceAccountData
    {
        if (count($character->groups) === 2) {
            return null;
        } else {
            return new ServiceAccountData($character->id, 'u2', 'p2', 'e2', ServiceAccountData::STATUS_ACTIVE);
        }
    }
}
