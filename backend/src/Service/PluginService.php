<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Entity\Plugin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\PluginConfiguration;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class PluginService
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

    public function getPlugin(int $id): ?Plugin
    {
        $plugin = $this->repositoryFactory->getPluginRepository()->find($id);
        if (!$plugin) {
            return null;
        }

        try {
            $this->addConfigurationFromFile($plugin);
        } catch (RuntimeException) {
            return null;
        }

        return $plugin;
    }

    /**
     * Returns active plugins with implementation, if available.
     *
     * @param int[] $limitToIds
     * @return Plugin[] Contains the implementation if it could be found.
     */
    public function getPluginWithImplementation(array $limitToIds = []): array
    {
        $result = [];
        foreach ($this->repositoryFactory->getPluginRepository()->findBy([], ['name' => 'asc']) as $plugin) {
            if (!$plugin->getConfigurationDatabase()?->active) {
                continue;
            }
            if (!empty($limitToIds) && !in_array($plugin->getId(), $limitToIds)) {
                continue;
            }
            $implementation = $this->getPluginImplementation($plugin);
            if ($implementation instanceof ServiceInterface) {
                $plugin->setServiceImplementation($implementation);
            }
            $result[] = $plugin;
        }
        return $result;
    }

    public function getPluginImplementation(Plugin $plugin): ?ServiceInterface
    {
        if (!$plugin->getConfigurationFile()) {
            try {
                $this->addConfigurationFromFile($plugin);
            } catch (RuntimeException) {
                return null;
            }
        }

        $pluginConfigDb = $plugin->getConfigurationDatabase();
        $pluginConfigYaml = $plugin->getConfigurationFile();
        if (!$pluginConfigDb || !$pluginConfigYaml) {
            return null;
        }

        // configure autoloader
        $psr4Path = '';
        if (is_string($this->config['plugins_install_dir'])) {
            $psr4Path = $this->config['plugins_install_dir'] . DIRECTORY_SEPARATOR . $pluginConfigDb->directoryName .
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
        $obj = new $phpClass(
            $this->log,
            new PluginConfiguration(
                $plugin->getId(),
                array_map('intval', $pluginConfigDb->requiredGroups),
                $pluginConfigDb->configurationData
            )
        );

        return $obj instanceof ServiceInterface ? $obj : null;
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
                $this->log->error("ServiceInterface::getAccounts must return an array of AccountData objects.");
                continue;
            }
            if (!in_array($account->getCharacterId(), $characterIds)) {
                if ($logErrorOnCharacterMismatch) {
                    $this->log->error('PluginService::getAccounts: Character ID does not match.');
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

        $plugins = $this->repositoryFactory->getPluginRepository()->findBy([]);
        foreach ($plugins as $plugin) {
            try {
                $this->addConfigurationFromFile($plugin);
            } catch (RuntimeException) {
                continue;
            }

            // Check if service has the "update-account" action
            $actions = $plugin->getConfigurationFile() ? $plugin->getConfigurationFile()->actions : [];
            if (!in_array(PluginConfigurationFile::ACTION_UPDATE_ACCOUNT, $actions)) {
                continue;
            }

            $implementation = $this->getPluginImplementation($plugin);
            if (!$implementation instanceof ServiceInterface) {
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
                        'serviceName' => $plugin->getName(),
                        'characterId' => $account->getCharacterId()
                    ];
                } else {
                    $serviceName = $plugin->getName();
                    $this->log->error("PluginService::updatePlayerAccounts: $serviceName: $error");
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
    private function addConfigurationFromFile(Plugin $plugin): void
    {
        $dirName = $plugin->getConfigurationDatabase()?->directoryName;
        if (empty($dirName)) {
            return;
        }

        $yamlConfig = $this->getConfigurationFromConfigFile($dirName);
        if (!$yamlConfig) {
            throw new RuntimeException('Cannot read YAML file.');
        }

        $plugin->setConfigurationFile($yamlConfig);
    }
}
