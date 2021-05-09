<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Character;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\OAuthToken;
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

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var Account
     */
    private $charService;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var int
     */
    private $sleep;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        Account $charService,
        OAuthToken $tokenService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        StorageInterface $storage
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage, $logger);

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->charService = $charService;
        $this->tokenService = $tokenService;
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
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each check',
                '50'
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $charId = intval($input->getArgument('character'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "check-tokens"', false);
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
                $charIds = array_map(function (Character $char) {
                    return $char->getId();
                }, $this->charRepo->findBy([], ['lastUpdate' => 'ASC'], $dbResultLimit, $offset));
            }

            foreach ($charIds as $charId) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('CheckTokens: cannot continue without an open entity manager.');
                    break;
                }
                $this->entityManager->clear(); // detaches all objects from Doctrine
                $this->checkForErrors();

                $char = $this->charRepo->find($charId);
                if ($char === null) {
                    $this->writeLine('Character ' . $charId.': not found.');
                } else {

                    // check token, corporation Doomheim and character owner hash - this may delete the character!
                    $result = $this->charService->checkCharacter($char, $this->tokenService);
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
}
