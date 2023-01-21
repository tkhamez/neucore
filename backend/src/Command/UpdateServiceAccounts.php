<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceInterface;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\AccountGroup;
use Neucore\Service\PluginService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateServiceAccounts extends Command
{
    use LogOutput;

    private EntityManagerInterface $entityManager;

    private CharacterRepository $characterRepository;

    private PlayerRepository $playerRepository;

    private PluginService $pluginService;

    private AccountGroup $accountGroup;

    private ?int $accountsUpdated = null;

    private ?int $updatesFailed = null;

    private ?int $charactersOrPlayersNotFound = null;

    public function __construct(
        LoggerInterface        $logger,
        EntityManagerInterface $entityManager,
        RepositoryFactory      $repositoryFactory,
        PluginService          $pluginService,
        AccountGroup           $accountGroup
    ) {
        parent::__construct();
        $this->logOutput($logger);

        $this->entityManager = $entityManager;
        $this->characterRepository = $repositoryFactory->getCharacterRepository();
        $this->playerRepository = $repositoryFactory->getPlayerRepository();
        $this->pluginService = $pluginService;
        $this->accountGroup = $accountGroup;
    }

    protected function configure(): void
    {
        $this->setName('update-service-accounts')
            ->setDescription('Updates accounts from service registration plugins.')
            ->addArgument('service', InputArgument::OPTIONAL, 'ID of one service to update.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update',
                '25'
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executeLogOutput($input, $output);
        $serviceId = intval($input->getArgument('service'));
        $sleep = intval($input->getOption('sleep'));

        $this->writeLine('Started "update-service-accounts"', false);

        $limit = $serviceId ? [$serviceId] : [];
        foreach ($this->pluginService->getActivePlugins($limit) as $plugin) {
            $implementation = $this->pluginService->getPluginImplementation($plugin);
            if ($implementation instanceof ServiceInterface) {
                $this->writeLine('  Updating '. $plugin->getName() . ' ...', false);
                $this->updateService($plugin->getName(), $implementation, $sleep);
            }
            unset($implementation); // Allows the plugin to free up resources.
        }

        $this->writeLine('Finished "update-service-accounts"', false);

        return 0;
    }

    private function updateService(string $serviceName, ServiceInterface $implementation, int $sleep): void
    {
        try {
            $allAccounts = $implementation->getAllAccounts();
        } catch (Exception) {
            $this->writeLine('  Could not get accounts for ' . $serviceName);
            return;
        }
        try {
            $allPlayerAccounts = $implementation->getAllPlayerAccounts();
        } catch (Exception) {
            $this->writeLine('  Could not get accounts for ' . $serviceName);
            return;
        }

        $this->accountsUpdated = 0;
        $this->updatesFailed = 0;
        $this->charactersOrPlayersNotFound = 0;

        foreach (array_values($allAccounts) as $i => $characterId) {
            $this->updateAccount((int)$characterId, $implementation);
            if ($i % 100 === 0) {
                $this->entityManager->clear(); // reduce memory usage
            }
            usleep($sleep * 1000); // reduce CPU usage
        }
        foreach (array_values($allPlayerAccounts) as $i => $playerId) {
            $this->updatePlayerAccount((int)$playerId, $implementation);
            if ($i % 100 === 0) {
                $this->entityManager->clear(); // reduce memory usage
            }
            usleep($sleep * 1000); // reduce CPU usage
        }

        $this->writeLine(
            "  Updated $serviceName: " .
            "$this->accountsUpdated accounts updated, " .
            "$this->updatesFailed updates failed, " .
            "$this->charactersOrPlayersNotFound characters or players not found."
        );
    }

    private function updateAccount(int $characterId, ServiceInterface $implementation): void
    {
        $character = $this->characterRepository->find($characterId);
        $main = null;
        if ($character) {
            if ($character->getPlayer()->getMain() !== null) {
                $main = $character->getPlayer()->getMain()->toCoreCharacter();
            }
            $coreCharacter = $character->toCoreCharacter();
            $coreGroups = $this->accountGroup->getCoreGroups($character->getPlayer());
        } else {
            $this->charactersOrPlayersNotFound++;
            $coreCharacter = new CoreCharacter($characterId, 0);
            $coreGroups = [];
        }

        try {
            $implementation->updateAccount($coreCharacter, $coreGroups, $main);
        } catch (Exception $e) {
            if ($e->getMessage() !== '') {
                $this->writeLine('  ' . $e->getMessage());
            }
            $this->updatesFailed++;
            return;
        }
        $this->accountsUpdated++;
    }

    private function updatePlayerAccount(int $playerId, ServiceInterface $implementation): void
    {
        $player = $this->playerRepository->find($playerId);
        if ($player && $player->getMain() !== null) {
            $coreCharacterMain = $player->getMain()->toCoreCharacter();
            $coreGroups = $this->accountGroup->getCoreGroups($player);
        } else {
            $this->charactersOrPlayersNotFound++;
            $coreCharacterMain = new CoreCharacter(0, $playerId);
            $coreGroups = [];
        }

        try {
            $implementation->updatePlayerAccount($coreCharacterMain, $coreGroups);
        } catch (Exception $e) {
            if ($e->getMessage() !== '') {
                $this->writeLine('  ' . $e->getMessage());
            }
            $this->updatesFailed++;
            return;
        }
        $this->accountsUpdated++;
    }
}
