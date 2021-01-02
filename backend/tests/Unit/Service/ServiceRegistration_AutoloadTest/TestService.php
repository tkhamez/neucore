<?php

declare(strict_types=1);

namespace Tests\ServiceRegistration_AutoloadTest;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    /**
     * @param CoreCharacter[] $characters
     * @param CoreGroup[] $groups
     * @return ServiceAccountData[]
     */
    public function getAccounts(array $characters, array $groups): array
    {
        return [];
    }

    /**
     * @param CoreGroup[] $groups
     * @param int[] $otherCharacterIds
     * @return ServiceAccountData
     * @throws Exception
     */
    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $otherCharacterIds
    ): ServiceAccountData {
        throw new Exception();
    }
}
