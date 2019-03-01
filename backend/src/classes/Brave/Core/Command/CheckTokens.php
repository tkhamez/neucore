<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\Account;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckTokens extends Command
{
    /**
     * @var \Brave\Core\Repository\CharacterRepository
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

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        Account $charService,
        OAuthToken $tokenService,
        ObjectManager $objectManager
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->charService = $charService;
        $this->tokenService = $tokenService;
        $this->objectManager = $objectManager;
    }

    protected function configure()
    {
        $this->setName('check-tokens')
            ->setDescription(
                'Checks refresh token. ' .
                'If the character owner hash has changed or the character has been biomassed, it will be deleted.'
            )
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each check', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = (int) $input->getOption('sleep');
        $this->output = $output;

        $this->writeln('* Started "check-tokens"');

        $this->checkTokens();

        $this->writeln('* Finished "check-tokens"');
    }

    private function checkTokens()
    {
        $charIds = [];
        $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($chars as $char) {
            $charIds[] = $char->getId();
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
                    $this->writeln('Character ' . $charId.': token N/A');
                } elseif ($result === Account::CHECK_TOKEN_OK) {
                    $this->writeln('Character ' . $charId.': token OK');
                } elseif ($result === Account::CHECK_TOKEN_NOK) {
                    $this->writeln('Character ' . $charId.': token NOK');
                } elseif ($result === Account::CHECK_CHAR_DELETED) {
                    $this->writeln('Character ' . $charId.': character deleted');
                } elseif ($result === Account::CHECK_REQUEST_ERROR) {
                    $this->writeln('Character ' . $charId.': token request failed');
                } else {
                    $this->writeln('Character ' . $charId.': unknown result');
                }
            }

            usleep($this->sleep * 1000);
        }
    }

    private function writeln($text)
    {
        $this->output->writeln(date('Y-m-d H:i:s ') . $text);
    }
}
