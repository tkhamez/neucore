<?php declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTokens extends Command
{
    use OutputTrait;

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
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var int
     */
    private $sleep;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        Account $charService,
        OAuthToken $tokenService,
        ObjectManager $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->charService = $charService;
        $this->tokenService = $tokenService;
        $this->objectManager = $objectManager;
        $this->logger = $logger; // property is on the trait
    }

    protected function configure()
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
                50
            );
        $this->configureOutputTrait($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $charId = intval($input->getArgument('character'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeOutputTrait($input, $output);

        $this->writeln('Started "check-tokens"', false);
        $this->check($charId);
        $this->writeln('Finished "check-tokens"', false);
    }

    private function check($charId = 0)
    {
        if ($charId !== 0) {
            $charIds = [$charId];
        } else {
            $charIds = [];
            $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
            foreach ($chars as $char) {
                $charIds[] = $char->getId();
            }
        }

        foreach ($charIds as $charId) {
            $this->objectManager->clear(); // detaches all objects from Doctrine

            $char = $this->charRepo->find($charId);
            if ($char === null) {
                $this->writeln('Character ' . $charId.': not found.');
            } else {

                // check token, corporation Doomheim and character owner hash - this may delete the character!
                $result = $this->charService->checkCharacter($char, $this->tokenService);
                if ($result === Account::CHECK_TOKEN_NA) {
                    $this->writeln('  Character ' . $charId.': token N/A');
                } elseif ($result === Account::CHECK_TOKEN_OK) {
                    $this->writeln('  Character ' . $charId.': token OK');
                } elseif ($result === Account::CHECK_TOKEN_NOK) {
                    $this->writeln('  Character ' . $charId.': token NOK');
                } elseif ($result === Account::CHECK_CHAR_DELETED) {
                    $this->writeln('  Character ' . $charId.': character deleted');
                } elseif ($result === Account::CHECK_TOKEN_PARSE_ERROR) {
                    $this->writeln('  Character ' . $charId.': token parse error');
                } else {
                    $this->writeln('  Character ' . $charId.': unknown result');
                }
            }

            usleep($this->sleep * 1000);
        }
    }
}
