<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Character;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Service\Account;
use Neucore\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTokens extends Command
{
    use LogOutput;
    use EsiRateLimited;

    private const CHARACTER = '  Character ';

    private CharacterRepository $charRepo;

    private PlayerRepository $playerRepo;

    private SystemVariableRepository $sysVarRepo;

    private Account $charService;

    private EntityManagerInterface $entityManager;

    private ?string $characters = null;

    private int $sleep = 50;

    private array $activePlayerIds = [];

    public function __construct(
        RepositoryFactory $repositoryFactory,
        Account $charService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        StorageInterface $storage
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage, $logger);

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->sysVarRepo = $repositoryFactory->getSystemVariableRepository();
        $this->charService = $charService;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('check-tokens')
            ->setDescription(
                'Checks refresh token. ' .
                'If the character owner hash has changed or the character has been biomassed, it will be deleted.'
            )
            ->addArgument('character', InputArgument::OPTIONAL, 'Check only one char.')
            ->addOption(
                'characters',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Which characters should be checked: all, active, other. ' .
                    'Active refers to all characters added in the last x days (x comes from the ' .
                    '"Groups Deactivation" configuration) or from player accounts where at ' .
                    'least one character is a member of one of the alliances or corporations configured for ' .
                    'the "Groups Deactivation" feature.',
                'all'
            )
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each check',
                $this->sleep
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $charId = intval($input->getArgument('character'));
        $this->characters = $input->getOption('characters');
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine("Started \"check-tokens\" (characters: $this->characters)", false);
        $this->check($charId);
        $this->writeLine('Finished "check-tokens"', false);

        return 0;
    }

    private function check(int $characterId = 0): void
    {
        $dbResultLimit = 1000;
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            if ($characterId !== 0) {
                $charIds = [$characterId];
            } else {
                $charIds = $this->getCharacters($dbResultLimit, $offset);
            }
            foreach ($charIds as $charId) {
                if (!$this->entityManager->isOpen()) {
                    $this->logger->critical('CheckTokens: cannot continue without an open entity manager.');
                    break;
                }
                $this->entityManager->clear(); // detaches all objects from Doctrine
                $this->checkForErrors();

                $char = $this->charRepo->find($charId);
                if ($char === null) {
                    $this->writeLine('Character ' . $charId.': not found.');
                } else {
                    // Check token, corporation Doomheim and character owner hash - this may delete the character!
                    // This does not update the "lastUpdate" property from the character.
                    $result = $this->charService->checkCharacter($char);
                    if ($result === Account::CHECK_TOKEN_NA) {
                        $this->writeLine(self::CHARACTER . $charId.': token N/A');
                    } elseif ($result === Account::CHECK_TOKEN_OK) {
                        $this->writeLine(self::CHARACTER . $charId.': token OK');
                    } elseif ($result === Account::CHECK_TOKEN_NOK) {
                        $this->writeLine(self::CHARACTER . $charId.': token NOK');
                    } elseif ($result === Account::CHECK_CHAR_DELETED) {
                        $this->writeLine(self::CHARACTER . $charId.': character deleted');
                    } elseif ($result === Account::CHECK_TOKEN_PARSE_ERROR) {
                        $this->writeLine(self::CHARACTER . $charId.': token parse error');
                    } else {
                        $this->writeLine(self::CHARACTER . $charId.': unknown result');
                    }
                }

                usleep($this->sleep * 1000);
            }
        } while (count($charIds) === $dbResultLimit);
    }

    /**
     * @return int[]
     */
    private function getCharacters(int $dbResultLimit, int $offset): array
    {
        $characterIds = [];

        if ($this->characters === 'all') {
            $characterIds = array_map(function (Character $char) {
                return $char->getId();
            }, $this->charRepo->findBy([], [], $dbResultLimit, $offset));
        } else {
            if (empty($this->activePlayerIds)) {
                $days = 0;
                $daysVar = $this->sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_ACTIVE_DAYS);
                if ($daysVar !== null && !empty($daysVar->getValue())) {
                    $days = (int)$daysVar->getValue();
                }

                $allianceIds = [];
                $corpIds = [];
                $allianceVar = $this->sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES);
                $corporationVar = $this->sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS);
                if ($allianceVar !== null && !empty($allianceVar->getValue())) {
                    $allianceIds = array_map('intval', explode(',', $allianceVar->getValue()));
                }
                if ($corporationVar !== null && !empty($corporationVar->getValue())) {
                    $corpIds = array_map('intval', explode(',', $corporationVar->getValue()));
                }

                $playerIds1 = $this->playerRepo->findInAlliances($allianceIds);
                $playerIds2 = $this->playerRepo->findInCorporations($corpIds);
                $playerIds3 = $this->playerRepo->findPlayersOfRecentlyAddedCharacters($days);

                $this->activePlayerIds = array_unique(array_merge($playerIds1, $playerIds2, $playerIds3));
            }
            if ($this->characters === 'active') {
                $characterIds = $this->charRepo->getCharacterIdsFromPlayers(
                    $this->activePlayerIds,
                    $dbResultLimit,
                    $offset
                );
            } elseif ($this->characters === 'other') {
                $characterIds = $this->charRepo->getCharacterIdsNotFromPlayers(
                    $this->activePlayerIds,
                    $dbResultLimit,
                    $offset
                );
            }
        }

        return $characterIds;
    }
}
