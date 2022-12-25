<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class UpdateServiceAccountsTest_TestService implements ServiceInterface
{
    public static ?int $playerId = null;

    public function __construct(LoggerInterface $logger, ServiceConfiguration $serviceConfiguration)
    {
    }

    public function getAccounts(array $characters): array
    {
        throw new Exception();
    }

    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds
    ): ServiceAccountData {
        throw new Exception();
    }

    public function updateAccount(CoreCharacter $character, array $groups, ?CoreCharacter $mainCharacter): void
    {
        if ($character->id === 102) {
            throw new Exception('Test exception.');
        }
    }

    public function updatePlayerAccount(CoreCharacter $mainCharacter, array $groups): void
    {
        if ($mainCharacter->playerId === self::$playerId) {
            throw new Exception('updatePlayerAccount exception');
        }
    }

    public function moveServiceAccount(int $toPlayerId, int $fromPlayerId): bool
    {
        return true;
    }

    public function resetPassword(int $characterId): string
    {
        throw new Exception();
    }

    public function getAllAccounts(): array
    {
        return [101, 102];
    }

    public function getAllPlayerAccounts(): array
    {
        return [(int)self::$playerId, self::$playerId + 100];
    }

    public function request(
        CoreCharacter $coreCharacter,
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $groups
    ): ResponseInterface {
        throw new Exception();
    }

    public function onConfigurationChange(): void
    {
    }
}
