<?php

declare(strict_types=1);

namespace Neucore\DataFixtures;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;

/** @noinspection PhpUnused */
class WatchlistFixtureLoader implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $repository = RepositoryFactory::getInstance($manager)->getWatchlistRepository();

        $list1 = $repository->find(1);
        if ($list1 === null) {
            $list1 = new Watchlist();
            $list1->setId(1);
            $manager->persist($list1);
        }
        $list1->setName('auto-red-flags');

        $manager->flush();
    }
}
