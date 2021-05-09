<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Service;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceInterface;
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
     * @var EntityManagerInterface
     */
    private $entityManager;

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

    /**
     * @var int
     */
    private $accountsUpdated;

    /**
     * @var int
     */
    private $updatesFailed;

    /**
     * @var int
     */
    private $charactersNotFound;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        RepositoryFactory $repositoryFactory,
        ServiceRegistration $serviceRegistration
    ) {
        parent::__construct();
        $this->logOutput($logger);

        $this->entityManager = $entityManager;
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
                '35'
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executeLogOutput($input, $output);
        $sleep = intval($input->getOption('sleep'));

        $this->writeLine('Started "update-service-accounts"', false);

        foreach ($this->serviceRepository->findBy([], ['name' => 'ASC']) as $service) {
            $this->updateService($service, $sleep);
        }

        $this->writeLine('Finished "update-service-accounts"', false);

        return 0;
    }

    private function updateService(Service $service, int $sleep): void
    {
        $implementation = $this->serviceRegistration->getServiceImplementation($service);
        if ($implementation === null) {
            $this->writeLine('  Service implementation not found for ' . $service->getName());
            return;
        }

        try {
            $allAccounts = $implementation->getAllAccounts();
        } catch (Exception $e) {
            $this->writeLine('  Could not get accounts for ' . $service->getName());
            return;
        }

        $this->accountsUpdated = 0;
        $this->updatesFailed = 0;
        $this->charactersNotFound = 0;

        foreach (array_values($allAccounts) as $i => $characterId) {
            $this->updateAccount($characterId, $implementation);
            if ($i % 100 === 0) {
                $this->entityManager->clear(); // reduce memory usage
            }
            usleep($sleep * 1000); // reduce CPU usage
        }

        $this->writeLine(
            "  Updated {$service->getName()}: " .
            "{$this->accountsUpdated} accounts updated, " .
            "{$this->updatesFailed} updates failed, " .
            "{$this->charactersNotFound} without a character."
        );
    }

    private function updateAccount(int $characterId, ServiceInterface $implementation): void
    {
        $character = $this->characterRepository->find($characterId);
        if ($character) {
            $coreCharacter = $character->toCoreCharacter();
            $coreGroups = $this->serviceRegistration->getCoreGroups($character->getPlayer());
        } else {
            $this->charactersNotFound++;
            $coreCharacter = new CoreCharacter($characterId);
            $coreGroups = [];
        }

        try {
            $implementation->updateAccount($coreCharacter, $coreGroups);
        } catch (Exception $e) {
            $this->updatesFailed++;
            return;
        }
        $this->accountsUpdated++;
    }
}
