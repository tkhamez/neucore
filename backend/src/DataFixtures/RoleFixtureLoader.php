<?php declare(strict_types=1);

namespace Neucore\DataFixtures;

use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class RoleFixtureLoader implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $roleRepository = RepositoryFactory::getInstance($manager)->getRoleRepository();

        $roles = [
            1 => Role::USER,
            2 => Role::APP,
            3 => Role::USER_ADMIN,
            4 => Role::GROUP_ADMIN,
            5 => Role::GROUP_MANAGER,
            6 => Role::APP_ADMIN,
            7 => Role::APP_MANAGER,
            8 => Role::ESI,
            9 => Role::SETTINGS,
            10 => Role::TRACKING,
            11 => Role::APP_TRACKING,
            12 => Role::APP_ESI,
            13 => Role::APP_GROUPS,
            14 => Role::APP_CHARS,
            15 => Role::USER_MANAGER,
            16 => Role::TRACKING_ADMIN,
        ];

        foreach ($roles as $id => $name) {
            $role = $roleRepository->find($id);
            if ($role === null) {
                $role = new Role($id);
                $manager->persist($role);
            }
            $role->setName($name);
        }

        $manager->flush();
    }
}
