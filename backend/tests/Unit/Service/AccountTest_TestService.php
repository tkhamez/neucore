<?php
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class AccountTest_TestService implements ServiceInterface
{
    public static array $updateAccount = [];

    public function __construct(LoggerInterface $logger, ServiceConfiguration $serviceConfiguration)
    {
    }

    public function getAccounts(array $characters): array
    {
        return array_map(function (CoreCharacter $character) {
            return new ServiceAccountData($character->id);
        }, $characters);
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
        self::$updateAccount[] = $character->id;
    }

    public function updatePlayerAccount(CoreCharacter $mainCharacter, array $groups): void
    {
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
        throw new Exception();
    }

    public function getAllPlayerAccounts(): array
    {
        throw new Exception();
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
