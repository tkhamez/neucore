<?php

namespace Tests\Unit\Entity;

use Neucore\Plugin\Core\FactoryInterface;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\PluginConfiguration;
use Neucore\Plugin\Data\ServiceAccountData;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ServiceTest_ServiceImplementation implements ServiceInterface
{
    public function __construct(
        LoggerInterface $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface $factory,
    ) {}

    public function onConfigurationChange(): void {}

    public function request(
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?CoreAccount $coreAccount,
    ): ResponseInterface {
        throw new Exception();
    }

    public function getAccounts(array $characters): array
    {
        return [];
    }

    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds,
    ): ServiceAccountData {
        throw new Exception();
    }

    public function updateAccount(CoreCharacter $character, array $groups, ?CoreCharacter $mainCharacter): void {}

    public function updatePlayerAccount(CoreCharacter $mainCharacter, array $groups): void {}

    public function moveServiceAccount(int $toPlayerId, int $fromPlayerId): bool
    {
        return false;
    }

    public function resetPassword(int $characterId): string
    {
        return '';
    }

    public function getAllAccounts(): array
    {
        return [];
    }

    public function getAllPlayerAccounts(): array
    {
        return [];
    }

    public function search(string $query): array
    {
        return [];
    }
}
