<?php

declare(strict_types=1);

namespace Neucore\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;

class WatchlistFixtureLoader
{
    public function load(ObjectManager $manager): void
    {
        $repository = RepositoryFactory::getInstance($manager)->getWatchlistRepository();

        $list1 = $repository->find(1);
        if ($list1 === null) {
            $list1 = new Watchlist();
            $list1->setId(1);
            $list1->setName('List 1');
            $manager->persist($list1);
        }

        $manager->flush();
    }
}
