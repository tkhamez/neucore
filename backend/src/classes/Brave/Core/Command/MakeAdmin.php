<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
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
    private $charRepo;

    /**
     * @var \Brave\Core\Repository\RoleRepository
     */
    private $roleRepo;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, ObjectManager $objectManager)
    {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->roleRepo = $repositoryFactory->getRoleRepository();
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
        $charId = (int) $input->getArgument('id');

        $char = $this->charRepo->find($charId);
        if ($char === null) {
            $output->writeln('Character with ID "' . $charId .'" not found');
            return;
        }

        $player = $char->getPlayer();
        if ($player === null) {
            $output->writeln('Player not found for character.');
            return;
        }

        $newRoles = [
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::USER_ADMIN,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING,
        ];
        foreach ($this->roleRepo->findBy(['name' => $newRoles]) as $newRole) {
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
