<?php

declare(strict_types=1);

namespace Neucore\Command;

use Eve\Sso\AuthenticationProvider;
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
    private CharacterRepository $charRepo;

    private OAuthToken $tokenService;

    private AuthenticationProvider $authenticationProvider;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        OAuthToken $tokenService,
        AuthenticationProvider $authenticationProvider,
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->tokenService = $tokenService;
        $this->authenticationProvider = $authenticationProvider;
    }

    protected function configure(): void
    {
        $this->setName('revoke-token')
            ->setDescription('Revoke a refresh token.')
            ->addArgument('id', InputArgument::REQUIRED, 'EVE character ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        try {
            $this->authenticationProvider->revokeRefreshToken($token);
        } catch (\Throwable $e) {
            $output->writeln('Error: ' . $e->getMessage());
            return 0;
        }

        $output->writeln('Success.');
        return 0;
    }
}
