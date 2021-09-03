<?php

declare(strict_types=1);

namespace Tests\Unit\Service\ServiceRegistration_AutoloadTest;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class TestService implements ServiceInterface
{
    /**
     * @var string
     */
    private $configurationData;

    public function __construct(LoggerInterface $logger, string $configurationData)
    {
        $this->configurationData = $configurationData;
    }

    public function getConfigurationData(): string
    {
        return $this->configurationData;
    }

    public function getAccounts(array $characters, array $groups): array
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
