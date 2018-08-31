<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Roles;
use Brave\Core\Service\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeAdmin extends Command
{
    /**
     * @var \Brave\Core\Repository\CharacterRepository
     */
    private $cr;

    /**
     * @var \Brave\Core\Repository\RoleRepository
     */
    private $rr;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, ObjectManager $objectManager)
    {
        parent::__construct();

        $this->cr = $repositoryFactory->getCharacterRepository();
        $this->rr = $repositoryFactory->getRoleRepository();
        $this->objectManager = $objectManager;
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
            Roles::USER_ADMIN,
            Roles::ESI,
        ];
        foreach ($this->rr->findBy(['name' => $newRoles]) as $newRole) {
            if (! $player->hasRole($newRole->getName())) {
                $player->addRole($newRole);
            }
        }

        if (! $this->objectManager->flush()) {
            return;
        }

        $output->writeln('Added all applicable roles to the player account "' .$player->getName() . '"');
    }
}
