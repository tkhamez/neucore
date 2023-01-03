<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Entity\Service;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class ServiceRegistration
{
    private LoggerInterface $log;

    private RepositoryFactory $repositoryFactory;

    private AccountGroup $accountGroup;

    private Config $config;

    private Parser $parser;

    public function __construct(
        LoggerInterface $log,
        RepositoryFactory $repositoryFactory,
        AccountGroup $accountGroup,
        Config $config,
        Parser $parser,
    ) {
        $this->log = $log;
        $this->accountGroup = $accountGroup;
        $this->repositoryFactory = $repositoryFactory;
        $this->config = $config;
        $this->parser = $parser;
    }

    public function getConfigurationFromConfigFile(string $pluginDirectory): ?PluginConfigurationFile
    {
        $basePath = is_string($this->config['plugins_install_dir']) ? $this->config['plugins_install_dir'] : '';
        $fullPathToFile = $basePath . DIRECTORY_SEPARATOR . $pluginDirectory . '/plugin.yml';

        if (!file_exists($fullPathToFile)) {
            $this->log->error("File does not exist $fullPathToFile");
            return null;
        }

        try {
            $yaml = $this->parser->parseFile($fullPathToFile);
        } catch (ParseException $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }

        if (!is_array($yaml)) {
            $this->log->error("Invalid file content in $fullPathToFile");
            return null;
        }

        $pluginConfigFile = PluginConfigurationFile::fromArray($yaml);
        $pluginConfigFile->directoryName = $pluginDirectory;

        return $pluginConfigFile;
    }

    public function getService(int $id): ?Service
    {
        $service = $this->repositoryFactory->getServiceRepository()->find($id);
        if (!$service) {
            return null;
        }

        try {
            $this->addConfigurationFromFile($service);
        } catch (RuntimeException) {
            return null;
        }

        return $service;
    }

    /**
     * Returns service implementation of active services.
     *
     * @param int[] $limitToIds
     * @return Service[] Contains the implementation if it could be found.
     */
    public function getServicesWithImplementation(array $limitToIds = []): array
    {
        $result = [];
        foreach ($this->repositoryFactory->getServiceRepository()->findBy([]) as $service) {
            if (!$service->getConfigurationDatabase()?->active) {
                continue;
            }
            if (!empty($limitToIds) && !in_array($service->getId(), $limitToIds)) {
                continue;
            }
            $implementation = $this->getServiceImplementation($service);
            $service->setImplementation($implementation);
            $result[] = $service;
        }
        return $result;
    }

    public function getServiceImplementation(Service $service): ?ServiceInterface
    {
        if (!$service->getConfigurationFile()) {
            try {
                $this->addConfigurationFromFile($service);
            } catch (RuntimeException) {
                return null;
            }
        }

        $serviceConfig = $service->getConfigurationDatabase();
        $pluginConfigYaml = $service->getConfigurationFile();
        if (!$serviceConfig || !$pluginConfigYaml) {
            return null;
        }

        // configure autoloader
        $psr4Path = '';
        if (is_string($this->config['plugins_install_dir'])) {
            $psr4Path = $this->config['plugins_install_dir'] . DIRECTORY_SEPARATOR . $serviceConfig->directoryName .
                DIRECTORY_SEPARATOR . $pluginConfigYaml->psr4Path;
        }
        if (!empty($pluginConfigYaml->psr4Prefix) && !empty($psr4Path) && is_dir($psr4Path)) {
            $psr4Paths = [$psr4Path];
            if (!str_ends_with($pluginConfigYaml->psr4Prefix, '\\')) {
                $pluginConfigYaml->psr4Prefix .= '\\';
            }
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            /* @var \Composer\Autoload\ClassLoader $loader */
            /** @noinspection PhpIncludeInspection */
            $loader = require Application::ROOT_DIR . '/vendor/autoload.php';
            foreach ($loader->getPrefixesPsr4() as $existingPrefix => $paths) {
                if ($existingPrefix === $pluginConfigYaml->psr4Prefix) {
                    $psr4Paths = array_merge($paths, $psr4Paths);
                }
            }
            try {
                $loader->setPsr4($pluginConfigYaml->psr4Prefix, $psr4Paths);
            } catch (\InvalidArgumentException) {
                // psr4 prefix ends this \ is checked above
            }
        }

        $phpClass = $pluginConfigYaml->phpClass;

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
                array_map('intval', $serviceConfig->requiredGroups),
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
            try {
                $this->addConfigurationFromFile($service);
            } catch (RuntimeException) {
                continue;
            }

            // Check if service has the "update-account" action
            $actions = $service->getConfigurationFile() ? $service->getConfigurationFile()->actions : [];
            if (!in_array(PluginConfigurationFile::ACTION_UPDATE_ACCOUNT, $actions)) {
                continue;
            }

            $implementation = $this->getServiceImplementation($service);
            if (!$implementation) {
                continue;
            }

            if ($from) {
                try {
                    $implementation->moveServiceAccount($player->getId(), $from->getId());
                } catch (Exception) {
                    // Ignore, plugin should log errors.
                }
            }

            $accounts = [];
            try {
                $accounts = $this->getAccounts($implementation, $player->getCharacters());
            } catch (Exception) {
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

    /**
     * @throws RuntimeException
     */
    private function addConfigurationFromFile(Service $service): void
    {
        $dirName = $service->getConfigurationDatabase()?->directoryName;
        if (empty($dirName)) {
            return;
        }

        $yamlConfig = $this->getConfigurationFromConfigFile($dirName);
        if (!$yamlConfig) {
            throw new RuntimeException('Cannot read YAML file.');
        }

        $service->setConfigurationFile($yamlConfig);
    }
}
