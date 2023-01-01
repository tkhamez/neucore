<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnused */
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service\ServiceRegistration\plugin\src;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TestService1 implements ServiceInterface
{
    public static bool $getAccountException = false;

    public static ?string $moved = null;

    public function __construct(LoggerInterface $logger, ServiceConfiguration $serviceConfiguration)
    {
    }

    public function getAccounts(array $characters): array
    {
        if ($characters[0]->id === 202) {
            self::$getAccountException = true;
            throw new Exception();
        }
        if ($characters[0]->id === 101) {
            return [
                new ServiceAccountData(101),
                new ServiceAccountData(102),
            ];
        }
        return [
            new ServiceAccountData($characters[0]->id, 'u', 'p', 'e'),
            [],
            new ServiceAccountData(123456),
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

    public function updateAccount(CoreCharacter $character, array $groups, ?CoreCharacter $mainCharacter): void
    {
        if ($character->id === 102) {
            throw new Exception('Test error');
        }
    }

    public function updatePlayerAccount(CoreCharacter $mainCharacter, array $groups): void
    {
    }

    public function moveServiceAccount(int $toPlayerId, int $fromPlayerId): bool
    {
        self::$moved = "$fromPlayerId -> $toPlayerId";
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