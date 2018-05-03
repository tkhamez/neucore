<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Service\OAuthToken;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TokenVerify extends Command
{
    /**
     * @var OAuthToken
     */
    private $tokenService;

    public function __construct(OAuthToken $tokenService)
    {
        parent::__construct();

        $this->tokenService = $tokenService;
    }

    protected function configure()
    {
        $this->setName('token-verify')
            ->setDescription('Verifies an ESI refresh token.')
            ->addArgument('token', InputArgument::REQUIRED, 'The token to verify.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token = $input->getArgument('token');

        $char = (new Character())
            ->setAccessToken('invalid')
            ->setExpires(time() - 1800)
            ->setRefreshToken($token)
        ;

        $this->tokenService->setCharacter($char);
        $owner = $this->tokenService->verify();

        if ($owner) {
            $output->writeln(print_r($owner->toArray(), true));
        } else {
            $output->writeln('Error, check log.');
        }
    }
}
