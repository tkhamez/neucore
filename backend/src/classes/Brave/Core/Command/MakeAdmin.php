<?php
namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MakeAdmin extends Command
{
    private $cr;

    private $rr;

    private $em;

    private $log;

    public function __construct(CharacterRepository $cr, RoleRepository $rr, EntityManagerInterface $em,
        LoggerInterface $log)
    {
        parent::__construct();

        $this->cr = $cr;
        $this->rr = $rr;
        $this->em = $em;
        $this->log = $log;
    }

    protected function configure()
    {
        $this->setName('make-admin')
            ->setDescription(
                'Adds all available roles to the player account to which '.
                'the character with the ID from the argument belongs.')
            ->addArgument('id', InputArgument::REQUIRED, 'Character ID.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arg = $input->getArgument('id');

        $char = $this->cr->find($arg);
        if ($char === null) {
            $output->writeln('Character with ID "' . $arg .'" not found');
            return;
        }

        $player = $char->getPlayer();

        $newRoles = [
            Roles::APP_ADMIN,
            Roles::APP_MANAGER,
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER_ADMIN
        ];
        foreach ($this->rr->findBy(['name' => $newRoles]) as $newRole) {
            if (! $player->hasRole($newRole->getName())) {
                $player->addRole($newRole);
            }
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return;
        }

        $output->writeln('Added all applicable roles to the player account "' .$player->getName() . '"');
    }
}
