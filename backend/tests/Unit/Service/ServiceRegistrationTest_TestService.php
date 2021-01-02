<?php
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistrationTest_TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    /**
     * @param CoreCharacter[] $characters
     * @param CoreGroup[] $groups
     * @return ServiceAccountData[]
     * @throws Exception
     */
    public function getAccounts(array $characters, array $groups): array
    {
        if ($characters[0]->id === 999) {
            throw new Exception();
        }
        return [
            new ServiceAccountData($characters[0]->id, 'u', 'p', 'e'),
            [],
            new ServiceAccountData(123456),
        ];
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
