<?php
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistrationTest_TestService implements ServiceInterface
{
    public static $lastGroup;

    public function __construct(LoggerInterface $logger, string $configurationData)
    {
    }

    public function getAccounts(array $characters, array $groups): array
    {
        self::$lastGroup = $groups;
        if ($characters[0]->id === 999) {
            throw new Exception();
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

    public function updateAccount(CoreCharacter $character, array $groups): void
    {
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
