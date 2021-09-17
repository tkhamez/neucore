<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ServiceControllerTest_TestService implements ServiceInterface
{
    public static $lastGroups;

    public static $throw = false;

    public function __construct(LoggerInterface $logger, ServiceConfiguration $serviceConfiguration)
    {
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

    public function request(
        CoreCharacter $coreCharacter,
        string $name, ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        throw new Exception();
    }
}
