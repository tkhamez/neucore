<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\DataFixtures\MiscFixtureLoader;
use Neucore\DataFixtures\RoleFixtureLoader;
use Neucore\DataFixtures\SystemVariablesFixtureLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DoctrineFixturesLoad extends Command
{
    private ObjectManager $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        parent::__construct();

        $this->objectManager = $objectManager;
    }

    protected function configure(): void
    {
        $this
            ->setName('doctrine-fixtures-load')
            ->setDescription(
                'Load data fixtures to the database. ' .
                'Appends the data fixtures instead of deleting all data from the database first.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('loading Neucore\DataFixtures\RoleFixtureLoader');
        (new RoleFixtureLoader())->load($this->objectManager);

        $output->writeln('loading Neucore\DataFixtures\SystemVariablesFixtureLoader');
        (new SystemVariablesFixtureLoader())->load($this->objectManager);

        $output->writeln('loading Neucore\DataFixtures\MiscFixtureLoader');
        (new MiscFixtureLoader())->load($this->objectManager);

        return 0;
    }
}
