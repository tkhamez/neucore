<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\EveService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Sample extends Command
{
    private $cr;

    private $es;

    public function __construct(CharacterRepository $cr, EveService $es)
    {
        parent::__construct();

        $this->cr = $cr;
        $this->es = $es;
    }

    protected function configure()
    {
        $this->setName('esi-example');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $chars = $this->cr->findBy([], null, 1);
        if (! isset($chars[0])) {
            return;
        }

        $this->es->setCharacter($chars[0]);
        $apiInstance = new \Swagger\Client\Eve\Api\CharacterApi(null, $this->es->getConfiguration());

        try {
            $result = $apiInstance->getCharactersCharacterId($chars[0]->getId());
        } catch (\Exception $e) {
            $result = $e->getMessage();
        }

        $output->writeln(print_r($result, true));
    }
}
