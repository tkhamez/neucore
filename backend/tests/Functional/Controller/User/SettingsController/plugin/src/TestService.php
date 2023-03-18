<?php
/** @noinspection PhpUnused */
/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Functional\Controller\User\SettingsController;

use Neucore\Plugin\Core\OutputInterface;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreRole;
use Neucore\Plugin\Exception;
use Neucore\Plugin\Core\FactoryInterface;
use Neucore\Plugin\GeneralInterface;
use Neucore\Plugin\Data\NavigationItem;
use Neucore\Plugin\Data\ServiceAccountData;
use Neucore\Plugin\Data\PluginConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TestService implements GeneralInterface, ServiceInterface
{
    public static ?int $groupId = null;

    public static ?int $managerGroupId = null;

    public function __construct(
        LoggerInterface     $logger,
        PluginConfiguration $pluginConfiguration,
        FactoryInterface    $factory,
    )
    {
    }

    public function onConfigurationChange(): void
    {
    }

    public function request(
        string                 $name,
        ServerRequestInterface $request,
        ResponseInterface      $response,
        ?CoreAccount           $coreAccount,
    ): ResponseInterface
    {
        throw new Exception();
    }

    public function getNavigationItems(): array
    {
        return [
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t1', '_blank', [CoreRole::GROUP_MANAGER]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', 'http://invalid', '_blank', [CoreRole::USER]),
            new NavigationItem('invalid', 'Test', '/t2', '_blank', [CoreRole::USER]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t3', groups: [-1]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t4', groups: [self::$groupId]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t5', managerGroups: [-1]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t6', managerGroups: [self::$managerGroupId]),
            new NavigationItem(NavigationItem::PARENT_ROOT, 'Test', '/t7', roles: [CoreRole::ANONYMOUS]),
        ];
    }

    public function command(array $arguments, array $options, OutputInterface $output): void
    {
    }

    public function getAccounts(array $characters): array
    {
        return [];
    }

    public function register(
        CoreCharacter $character,
        array         $groups,
        string        $emailAddress,
        array         $allCharacterIds
    ): ServiceAccountData
    {
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

    public function search(string $query): array
    {
        throw new Exception();
    }
}
