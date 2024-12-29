<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Repository;

namespace Tests\Unit\Repository;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\GroupRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class GroupRepositoryTest extends TestCase
{
    private static GroupRepository $repository;

    private static int $p1Id;

    private static int $p2Id;

    private static int $p3Id;

    private static int $p4Id;

    private static int $group1Id;

    private static int $group2Id;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        self::$repository = (new RepositoryFactory($om))->getGroupRepository();

        $group1 = (new Group())->setName('g1');
        $group2 = (new Group())->setName('g2');
        $player1 = (new Player())->setName('p1')->addGroup($group2);
        $player2 = (new Player())->setName('p2')->addGroup($group2);
        $player3 = (new Player())->setName('p3')->addGroup($group2);
        $player4 = (new Player())->setName('p4')->addGroup($group2);
        $alliance = (new Alliance())->setId(10)->setName('Alliance 10');
        $corporation1 = (new Corporation())->setId(100)->setName('Corp 100')->setAlliance($alliance);
        $corporation2 = (new Corporation())->setId(200)->setName('Corp 200');
        $character1a = (new Character())->setId(10)->setName('c1a')->setMain(false)->setPlayer($player1)
            ->setCorporation($corporation1);
        $character1b = (new Character())->setId(15)->setName('c1b')->setMain(true)->setPlayer($player1)
            ->setCorporation($corporation1);
        $character2 = (new Character())->setId(20)->setName('c2')->setMain(true)->setPlayer($player2)
            ->setCorporation($corporation2);
        $character3 = (new Character())->setId(30)->setName('c3')->setMain(false)->setPlayer($player3);

        $om->persist($group1);
        $om->persist($group2);
        $om->persist($player1);
        $om->persist($player2);
        $om->persist($player3);
        $om->persist($player4);
        $om->persist($alliance);
        $om->persist($corporation1);
        $om->persist($corporation2);
        $om->persist($character1a);
        $om->persist($character1b);
        $om->persist($character2);
        $om->persist($character3);
        $om->flush();

        self::$group1Id = $group1->getId();
        self::$group2Id = $group2->getId();
        self::$p1Id = $player1->getId();
        self::$p2Id = $player2->getId();
        self::$p3Id = $player3->getId();
        self::$p4Id = $player4->getId();
    }

    public function testGetMembersWithCorporationAndAlliance()
    {
        $this->assertSame([], self::$repository->getMembersWithCorporationAndAlliance(self::$group1Id));

        $this->assertSame([
            ['player_id' => self::$p1Id, 'player_name' => 'p1', 'character_id' => 15, 'corporation_id' => 100,
                'corporation_name' => 'Corp 100', 'alliance_id' => 10, 'alliance_name' => 'Alliance 10'],
            ['player_id' => self::$p2Id, 'player_name' => 'p2', 'character_id' => 20, 'corporation_id' => 200,
                'corporation_name' => 'Corp 200', 'alliance_id' => null, 'alliance_name' => null],
            ['player_id' => self::$p3Id, 'player_name' => 'p3', 'character_id' => null, 'corporation_id' => null,
                'corporation_name' => null, 'alliance_id' => null, 'alliance_name' => null],
            ['player_id' => self::$p4Id, 'player_name' => 'p4', 'character_id' => null, 'corporation_id' => null,
                'corporation_name' => null, 'alliance_id' => null, 'alliance_name' => null],
        ], self::$repository->getMembersWithCorporationAndAlliance(self::$group2Id));
    }
}
