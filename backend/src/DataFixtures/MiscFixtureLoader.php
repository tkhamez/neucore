<?php

declare(strict_types=1);

namespace Neucore\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;

class MiscFixtureLoader
{
    public function load(ObjectManager $manager): void
    {
        // Note: The logins are also added via migration files. This is only here if the database schema was generated
        // in a different way (which is not supported).

        $flush = false;
        $repository = RepositoryFactory::getInstance($manager)->getEveLoginRepository();

        $defaultLogin = $repository->findOneBy(['name' => EveLogin::NAME_DEFAULT]);
        if ($defaultLogin === null) {
            $login = new EveLogin();
            $login->setName(EveLogin::NAME_DEFAULT);
            $manager->persist($login);
            $flush = true;
        }

        $trackingLogin = $repository->findOneBy(['name' => EveLogin::NAME_TRACKING]);
        if ($trackingLogin === null) {
            $login = new EveLogin();
            $login->setName(EveLogin::NAME_TRACKING);
            $login->setDescription('Token to get the member tracking data from ESI.');
            $login->setEsiScopes(implode(' ', [
                EveLogin::SCOPE_ROLES,
                EveLogin::SCOPE_TRACKING,
                EveLogin::SCOPE_STRUCTURES,
            ]));
            $login->setEveRoles([EveLogin::ROLE_DIRECTOR]);
            $manager->persist($login);
            $flush = true;
        }

        if ($flush) {
            $manager->flush();
        }
    }
}
