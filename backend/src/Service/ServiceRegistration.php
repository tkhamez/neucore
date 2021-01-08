<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Service;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistration
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $userAuth;

    public function __construct(LoggerInterface $log, UserAuth $userAuth)
    {
        $this->log = $log;
        $this->userAuth = $userAuth;
    }

    public function hasRequiredGroups(Service $service): bool
    {
        $character = $this->userAuth->getUser();
        if ($character === null) {
            return false;
        }

        $serviceConfig = $service->getConfiguration();
        foreach ($serviceConfig->requiredGroups as $group) {
            $group = (int)$group;
            if ($group > 0 && !$character->getPlayer()->hasGroup($group)) {
                return false;
            }
        }
        return true;
    }

    public function getServiceImplementation(Service $service): ?ServiceInterface
    {
        $serviceConfig = $service->getConfiguration();

        // configure autoloader
        $psr4Paths = (array) $serviceConfig->psr4Path;
        if ($serviceConfig->psr4Prefix !== '' && $psr4Paths !== []) {
            if (substr($serviceConfig->psr4Prefix, -1) !== '\\') {
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

        if (!class_exists($serviceConfig->phpClass)) {
            return null;
        }

        $implements = class_implements($serviceConfig->phpClass);
        if (!is_array($implements) || !in_array(ServiceInterface::class, $implements)) {
            return null;
        }

        return new $serviceConfig->phpClass($this->log);
    }

    /**
     * @param ServiceInterface $service
     * @param Character[] $characters All character must belong to the same player.
     * @param bool $logErrorOnCharacterMismatch
     * @return ServiceAccountData[]
     * @throws Exception
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
        $coreGroups = $characters[0]->getPlayer()->getCoreGroups();
        foreach ($service->getAccounts($coreCharacters, $coreGroups) as $account) {
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
