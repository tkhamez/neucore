<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\ServiceRepository;
use Neucore\Service\ServiceRegistration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateServiceAccounts extends Command
{
    use LogOutput;

    /**
     * @var ServiceRepository
     */
    private $serviceRepository;

    /**
     * @var CharacterRepository
     */
    private $characterRepository;

    /**
     * @var ServiceRegistration
     */
    private $serviceRegistration;

    public function __construct(
        LoggerInterface $logger,
        RepositoryFactory $repositoryFactory,
        ServiceRegistration $serviceRegistration
    ) {
        parent::__construct();
        $this->logOutput($logger);

        $this->serviceRepository = $repositoryFactory->getServiceRepository();
        $this->characterRepository = $repositoryFactory->getCharacterRepository();
        $this->serviceRegistration = $serviceRegistration;
    }

    protected function configure(): void
    {
        $this->setName('update-service-accounts')
            ->setDescription('Updates accounts from service registration plugins.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update',
                35
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executeLogOutput($input, $output);
        $sleep = intval($input->getOption('sleep'));

        $this->writeLine('Started "update-service-accounts"', false);

        foreach ($this->serviceRepository->findBy([], ['name' => 'ASC']) as $service) {
            $implementation = $this->serviceRegistration->getServiceImplementation($service);
            if ($implementation === null) {
                $this->writeLine('  Service implementation not found for ' . $service->getName());
                continue;
            }

            try {
                $allAccounts = $implementation->getAllAccounts();
            } catch (Exception $e) {
                $this->writeLine('  Could not get accounts for ' . $service->getName());
                continue;
            }

            $charactersNotFound = 0;
            $accountsUpdated = 0;
            $updatesFailed = 0;

            foreach ($allAccounts as $characterId) {
                $character = $this->characterRepository->find($characterId);
                if ($character) {
                    $coreCharacter = $character->toCoreCharacter();
                    $coreGroups = $this->serviceRegistration->getCoreGroups($character->getPlayer());
                } else {
                    $charactersNotFound++;
                    $coreCharacter = new CoreCharacter($characterId);
                    $coreGroups = [];
                }

                try {
                    $implementation->updateAccount($coreCharacter, $coreGroups);
                } catch (Exception $e) {
                    $updatesFailed++;
                    continue;
                }
                $accountsUpdated++;

                usleep($sleep * 1000); // reduce CPU usage
            }

            $this->writeLine(
                "  Updated {$service->getName()}: " .
                "$accountsUpdated accounts updated, " .
                "$updatesFailed updates failed, " .
                "$charactersNotFound without a character."
            );
        }

        $this->writeLine('Finished "update-service-accounts"', false);

        return 0;
    }
}
