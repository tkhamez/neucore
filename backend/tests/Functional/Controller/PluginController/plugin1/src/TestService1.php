<?php
/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Controller\PluginController;

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

class TestService1 implements ServiceInterface
{
    public static array $data = [];

    public function __construct(
        LoggerInterface $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface $factory,
    ) {
        self::$data = [];
    }

    public function onConfigurationChange(): void
    {
    }

    public function request(
        string $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?CoreAccount $coreAccount,
    ): ResponseInterface {
        if (($request->getQueryParams()['error'] ?? '') === '1') {
            throw new Exception('Exception from service plugin.');
        }

        self::$data = [
            'name' => $name,
            'main' => $coreAccount?->main,
            'characters' => $coreAccount?->characters,
            'memberGroups' => $coreAccount?->getMemberGroups(),
            'groupsDeactivated' => $coreAccount?->groupsDeactivated,
            'managerGroups' => $coreAccount?->managerGroups,
            'roles' => $coreAccount?->roles,
        ];

        $response->getBody()->write('Response from plugin.');
        return $response;
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

    public function search(string $query): array
    {
        throw new Exception();
    }
}
