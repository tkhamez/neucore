<?php
/* @phan-file-suppress PhanTypeMismatchReturn */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistrationTest_TestService implements ServiceInterface
{
    public static $lastGroup;

    public function __construct(LoggerInterface $logger, ServiceConfiguration $serviceConfiguration)
    {
    }

    public function getAccounts(array $characters): array
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

    public function request(
        CoreCharacter $coreCharacter,
        string $name, ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        throw new Exception();
    }
}
