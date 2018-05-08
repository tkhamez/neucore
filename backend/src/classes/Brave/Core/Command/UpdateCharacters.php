<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\EsiCharacterService;
use Brave\Core\Service\OAuthToken;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class UpdateCharacters extends Command
{
    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var EsiCharacterService
     */
    private $charService;

    /**
     * @var OAuthToken
     */
    private $token;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(
        CharacterRepository $charRepo,
        EsiCharacterService $charService,
        OAuthToken $token,
        EntityManagerInterface $em,
        LoggerInterface $log
    ) {
        parent::__construct();

        $this->charRepo = $charRepo;
        $this->charService = $charService;
        $this->token = $token;
        $this->em = $em;
        $this->log = $log;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters from ESI and checks refresh token.')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each character update', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sleep = (int) $input->getOption('sleep');

        $charIds = [];
        $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($chars as $char) {
            $charIds[] = $char->getId();
        }

        foreach ($charIds as $charId) {
            $this->em->clear(); // detaches all objects from Doctrine
            usleep($sleep * 1000);

            // update name, corp and alliance from ESI
            $updatedChar = $this->charService->fetchCharacter($charId, true);
            if ($updatedChar === null) {
                $output->writeln($charId.': error updating.');
                continue;
            }

            // check refresh token, update character owner hash
            if ((string) $updatedChar->getRefreshToken() === '') {
                $output->writeln($charId.': update OK, token N/A');
                continue;
            }
            $this->token->setCharacter($updatedChar);
            $resourceOwner = $this->token->verify();
            if ($resourceOwner === null) {
                $updatedChar->setValidToken(false);
                $output->writeln($charId.': update OK, token NOK');
            } else {
                $data = $resourceOwner->toArray();
                if (isset($data['CharacterOwnerHash'])) {
                    $updatedChar->setCharacterOwnerHash($data['CharacterOwnerHash']);
                    $updatedChar->setValidToken(true);
                    $output->writeln($charId.': update OK, token OK');
                } else {
                    // that's an error, OAuth changed resource owner data
                    $this->log->error('Unexpected result from OAuth verify.', [
                        'data' => $data
                    ]);
                    $output->writeln($charId.': update OK, token UNKNOWN => check log');
                }
            }
            $this->em->flush();
        }

        $output->writeln('All done.');
    }
}
