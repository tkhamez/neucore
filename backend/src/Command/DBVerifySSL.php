<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\DBAL\Driver\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DBVerifySSL extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('db-verify-ssl')
            ->setDescription('Shows SSL cipher if DB connection is encrypted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $result = $this->entityManager->getConnection()
                ->executeQuery("SHOW SESSION STATUS LIKE 'Ssl_cipher'")
                ->fetchAllAssociative();
        } catch (Exception | \Doctrine\DBAL\Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }
        $output->writeln('Ssl_cipher: ' . $result[0]['Value']);
        return 0;
    }
}
