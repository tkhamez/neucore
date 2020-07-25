<?php

declare(strict_types=1);

namespace Neucore\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;

class RoleFixtureLoader
{
    public function load(ObjectManager $manager): void
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
            17 => Role::WATCHLIST,
            18 => Role::WATCHLIST_ADMIN,
            19 => Role::WATCHLIST_MANAGER,
            20 => Role::USER_CHARS,
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
