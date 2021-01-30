<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class UpdateServiceAccountsTest_TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    public function getAccounts(array $characters, array $groups): array
    {
        throw new Exception();
    }

    public function register(CoreCharacter $character, array $groups, string $emailAddress, array $allCharacterIds): ServiceAccountData
    {
        throw new Exception();
    }

    public function updateAccount(CoreCharacter $character, array $groups): void
    {
        if ($character->id === 102) {
            throw new Exception();
        }
    }

    public function resetPassword(int $characterId): string
    {
        throw new Exception();
    }

    public function getAllAccounts(): array
    {
        return [101, 102];
    }
}
