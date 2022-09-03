<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Data\ServiceConfiguration;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration as PluginServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistration
{
    private LoggerInterface $log;

    private RepositoryFactory $repositoryFactory;

    private AccountGroup $accountGroup;

    public function __construct(LoggerInterface $log, RepositoryFactory $repositoryFactory, AccountGroup $accountGroup)
    {
        $this->log = $log;
        $this->accountGroup = $accountGroup;
        $this->repositoryFactory = $repositoryFactory;
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
            new PluginServiceConfiguration(
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

    public function updatePlayerAccounts(Player $player, ?Player $from = null): array
    {
        $updated = [];

        $services = $this->repositoryFactory->getServiceRepository()->findBy([]);
        foreach ($services as $service) {
            // Check if service has the "update-account" action
            if (!in_array(ServiceConfiguration::ACTION_UPDATE_ACCOUNT, (array)$service->getConfiguration()->actions)) {
                continue;
            }

            $implementation = $this->getServiceImplementation($service);
            if (!$implementation) {
                continue;
            }

            if ($from) {
                try {
                    $implementation->moveServiceAccount($player->getId(), $from->getId());
                } catch (Exception $e) {
                    // Ignore, plugin should log errors.
                }
            }

            $accounts = [];
            try {
                $accounts = $this->getAccounts($implementation, $player->getCharacters());
            } catch (Exception $e) {
                // Do nothing, service should log its errors
            }

            foreach ($accounts as $account) {
                $character = $player->getCharacter($account->getCharacterId());
                $error = $this->updateServiceAccount($character, $implementation);
                if ($error === null) {
                    $updated[] = [
                        'serviceName' => $service->getName(),
                        'characterId' => $account->getCharacterId()
                    ];
                } else {
                    $serviceName = $service->getName();
                    $this->log->error("ServiceController::updateAllAccounts: $serviceName: $error");
                }
            }
        }

        return $updated;
    }

    /**
     * @return string|null Error message (can be empty) or null on success.
     */
    public function updateServiceAccount(?Character $character, ServiceInterface $serviceImplementation): ?string
    {
        if (!$character) {
            // Does not happen, but makes it easier in other places.
            return 'No character provided.';
        }

        $main = null;
        if ($character->getPlayer()->getMain() !== null) {
            $main = $character->getPlayer()->getMain()->toCoreCharacter();
        }

        try {
            $serviceImplementation->updateAccount(
                $character->toCoreCharacter(),
                $this->accountGroup->getCoreGroups($character->getPlayer()),
                $main
            );
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return null;
    }
}
