<?php declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Service\OAuthToken;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevokeToken extends Command
{
    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    public function __construct(RepositoryFactory $repositoryFactory, OAuthToken $tokenService)
    {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->tokenService = $tokenService;
    }

    protected function configure()
    {
        $this->setName('revoke-token')
            ->setDescription('Revoke a refresh token.')
            ->addArgument('id', InputArgument::REQUIRED, 'EVE character ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = intval($input->getArgument('id'));
        $character = $this->charRepo->find($id);
        if ($character === null) {
            $output->writeln('Character not found.');
            return null;
        }

        $token = $character->createAccessToken();
        if ($token === null) {
            $output->writeln('Character has no token.');
            return null;
        }

        if ($this->tokenService->revokeRefreshToken($token)) {
            $output->writeln('Success.');
        } else {
            $output->writeln('Error, check log.');
        }
    }
}
