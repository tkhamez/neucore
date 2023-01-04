<?php
/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Controller\User\SettingsController;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\PluginConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger, PluginConfiguration $pluginConfiguration)
    {
    }

    public function getAccounts(array $characters): array
    {
        return [];
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
        throw new Exception();
    }

    public function updatePlayerAccount(CoreCharacter $mainCharacter, array $groups): void
    {
        throw new Exception();
    }

    public function moveServiceAccount(int $toPlayerId, int $fromPlayerId): bool
    {
        return true;
    }

    public function resetPassword(int $characterId): string
    {
        throw new Exception();
    }

    public function getAllPlayerAccounts(): array
    {
        throw new Exception();
    }

    public function getAllAccounts(): array
    {
        throw new Exception();
    }

    public function request(
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        CoreCharacter $main,
        array $characters,
        array $memberGroups,
        array $managerGroups,
        array $roles,
    ): ResponseInterface {
        throw new Exception();
    }

    public function onConfigurationChange(): void
    {
    }

    public function search(string $query): array
    {
        throw new Exception();
    }
}
