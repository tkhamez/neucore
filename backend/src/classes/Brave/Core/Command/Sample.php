<?php

namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\EveService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Sample extends Command
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var CharacterRepository
     */
    private $cr;

    private $es;

    public function __construct(LoggerInterface $log, CharacterRepository $cr, EveService $es)
    {
        parent::__construct();

        $this->log = $log;
        $this->cr = $cr;
        $this->es = $es;
    }

    protected function configure()
    {
        $this->setName('sample')
            ->setDescription('Sample command')
            ->addArgument('arg', InputArgument::REQUIRED, 'Required argument.')
            ->addOption('opt', 'o', InputArgument::OPTIONAL, 'Optional option.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arg = $input->getArgument('arg');
        $opt = $input->getOption('opt');

        // EVE API example
        $chars = $this->cr->findBy([], null, 1);
        if (isset($chars[0])) {
            /* @var $char \Brave\Core\Entity\Character */
            $char = $chars[0];
            $this->es->setCharacter($char);
            $apiInstance = new \Swagger\Client\Eve\Api\CharacterApi(null, $this->es->getConfiguration());

            try {
                $result = $apiInstance->getCharactersCharacterId($char->getId());
                //print_r($result);
            } catch (\Exception $e) {
                $this->log->error(
                    'Exception when calling CharacterApi->getCharactersCharacterId',
                    ['exception' => $e]
                );
            }
        }

        $output->writeln('Sample command with arg: '.$arg.', opt: '.$opt.' done.');
    }
}
