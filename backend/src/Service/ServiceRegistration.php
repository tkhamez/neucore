<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Service;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistration
{
    private LoggerInterface $log;

    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }

    public function getServiceImplementation(Service $service): ?ServiceInterface
    {
        $serviceConfig = $service->getConfiguration();

        // configure autoloader
        $psr4Paths = (array) $serviceConfig->psr4Path;
        if (!empty($serviceConfig->psr4Prefix) && $psr4Paths !== []) {
            if (substr((string)$serviceConfig->psr4Prefix, -1) !== '\\') {
                $serviceConfig->psr4Prefix .= '\\';
            }
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            /* @var \Composer\Autoload\ClassLoader $loader */
            /** @noinspection PhpIncludeInspection */
            $loader = require Application::ROOT_DIR . '/vendor/autoload.php';
            foreach ($loader->getPrefixesPsr4() as $existingPrefix => $paths) {
                if ($existingPrefix === $serviceConfig->psr4Prefix) {
                    $psr4Paths = array_merge($paths, $psr4Paths);
                }
            }
            $loader->setPsr4($serviceConfig->psr4Prefix, $psr4Paths);
        }

        $phpClass = (string)$serviceConfig->phpClass;

        if (!class_exists($phpClass)) {
            return null;
        }

        $implements = class_implements($phpClass);
        if (!is_array($implements) || !in_array(ServiceInterface::class, $implements)) {
            return null;
        }

        // ServiceInterface::__construct
        return new $phpClass(
            $this->log,
            new ServiceConfiguration(
                $service->getId(),
                array_map('intval', (array)$serviceConfig->requiredGroups),
                $serviceConfig->configurationData
            )
        );
    }

    /**
     * @param ServiceInterface $service
     * @param Character[] $characters All character must belong to the same player.
     * @param bool $logErrorOnCharacterMismatch
     * @return ServiceAccountData[]
     * @throws Exception from the service implementation
     */
    public function getAccounts(
        ServiceInterface $service,
        array $characters,
        bool $logErrorOnCharacterMismatch = true
    ): array {
        if (empty($characters)) {
            return [];
        }

        $accountData = [];

        $coreCharacters = [];
        $characterIds = [];
        foreach ($characters as $character) {
            $coreCharacters[] = $character->toCoreCharacter();
            $characterIds[] = $character->getId();
        }
        foreach ($service->getAccounts($coreCharacters) as $account) {
            if (!$account instanceof ServiceAccountData) {
                $this->log->error(
                    "ServiceController: ServiceInterface::getAccounts must return an array of AccountData objects."
                );
                continue;
            }
            if (! in_array($account->getCharacterId(), $characterIds)) {
                if ($logErrorOnCharacterMismatch) {
                    $this->log->error("ServiceController: Character ID does not match.");
                }
                continue;
            }
            $accountData[] = $account;
        }

        return $accountData;
    }
}
