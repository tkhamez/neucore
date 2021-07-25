<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Entity\EveLogin;
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

    protected function configure(): void
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
            return 0;
        }

        $esiToken = $character->getEsiToken(EveLogin::NAME_DEFAULT);
        if ($esiToken === null) {
            $output->writeln('Character has no default token.');
            return 0;
        }
        $token = $this->tokenService->createAccessToken($esiToken);
        if ($token === null) {
            $output->writeln('Error reading token.');
            return 0;
        }

        if ($this->tokenService->revokeRefreshToken($token)) {
            $output->writeln('Success.');
        } else {
            $output->writeln('Error, check log.');
        }

        return 0;
    }
}
