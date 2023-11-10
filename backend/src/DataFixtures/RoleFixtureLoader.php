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
            13 => Role::APP_GROUPS,
            14 => Role::APP_CHARS,
            15 => Role::USER_MANAGER,
            16 => Role::TRACKING_ADMIN,
            17 => Role::WATCHLIST,
            18 => Role::WATCHLIST_ADMIN,
            19 => Role::WATCHLIST_MANAGER,
            20 => Role::USER_CHARS,
            21 => Role::PLUGIN_ADMIN,
            22 => Role::STATISTICS,
            23 => Role::APP_ESI_LOGIN,
            24 => Role::APP_ESI_PROXY,
            25 => Role::APP_ESI_TOKEN,
        ];

        foreach ($roles as $id => $name) {
            $role = $roleRepository->find($id);
            if ($role === null) {
                $role = new Role($id);
                $manager->persist($role);
            }
            $role->setName($name);
        }

        $rolesRemove = [
            12, // app-esi, removed in v2.4.0
        ];
        foreach ($rolesRemove as $roleId) {
            $roleRemove = $roleRepository->find($roleId);
            if ($roleRemove !== null) {
                $manager->remove($roleRemove);
            }
        }

        $manager->flush();
    }
}
