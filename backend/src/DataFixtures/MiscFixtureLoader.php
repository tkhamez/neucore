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
        $repository = RepositoryFactory::getInstance($manager)->getEveLoginRepository();

        $defaultLogin = $repository->findOneBy(['name' => EveLogin::NAME_DEFAULT]);
        if ($defaultLogin === null) {
            $login = new EveLogin();
            $login->setName(EveLogin::NAME_DEFAULT);
            $manager->persist($login);
            $manager->flush();
        }
    }
}
