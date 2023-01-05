<?php
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Controller\User\ServiceController;

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
    public static ?array $lastGroups = null;

    public static bool $throw = false;

    public function __construct(
        LoggerInterface $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface $factory,
    ) {
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
        throw new Exception();
    }

    public function getAccounts(array $characters): array
    {
        if (self::$throw) {
            throw new Exception();
        }
        return [
            new ServiceAccountData(1, 'u', 'p', 'e', ServiceAccountData::STATUS_ACTIVE, 'dn'),
            new ServiceAccountData(3),
        ];
    }

    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds
    ): ServiceAccountData {
        self::$lastGroups = $groups;
        if (count($groups) === 2 && $groups[1]->name === 'G3') {
            throw new Exception();
        } elseif (count($groups) === 2 && $groups[1]->name === 'G5') {
            throw new Exception('test');
        } else {
            return new ServiceAccountData($character->id, 'u2', 'p2', 'e2', ServiceAccountData::STATUS_ACTIVE, 'dn');
        }
    }

    public function updateAccount(CoreCharacter $character, array $groups, ?CoreCharacter $mainCharacter): void
    {
        self::$lastGroups = $groups;
        if ($character->id === 3) {
            throw new Exception();
        }
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
        if ($characterId === 3) {
            throw new Exception();
        } else {
            return 'new-pass';
        }
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
