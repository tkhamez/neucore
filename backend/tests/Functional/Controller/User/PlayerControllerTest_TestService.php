<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class PlayerControllerTest_TestService implements ServiceInterface
{
    public static $lastGroups;

    public function __construct(LoggerInterface $logger)
    {
    }

    public function getAccounts(array $characters, array $groups): array
    {
        return [
            new ServiceAccountData(12, 'user_name', null, null, ServiceAccountData::STATUS_ACTIVE),
        ];
    }

    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds
    ): ServiceAccountData {
        throw new Exception();
    }

    public function updateAccount(CoreCharacter $character, array $groups): void
    {
        throw new Exception();
    }

    public function resetPassword(int $characterId): string
    {
        throw new Exception();
    }

    public function getAllAccounts(): array
    {
        throw new Exception();
    }
}
