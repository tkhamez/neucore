<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

namespace Tests\Unit\Repository;

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
    /**
     * @var GroupRepository
     */
    private static $repository;

    /**
     * @var int
     */
    private static $p1Id;

    /**
     * @var int
     */
    private static $p2Id;

    /**
     * @var int
     */
    private static $p3Id;

    /**
     * @var int
     */
    private static $p4Id;

    /**
     * @var int
     */
    private static $group2Id;

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
        $corporation = (new Corporation())->setId(100)->setName('corp');
        $character1a = (new Character())->setId(10)->setName('c1a')->setMain(false)->setPlayer($player1)
            ->setCorporation($corporation);
        $character1b = (new Character())->setId(15)->setName('c1b')->setMain(true)->setPlayer($player1)
            ->setCorporation($corporation);
        $character2 = (new Character())->setId(20)->setName('c2')->setMain(true)->setPlayer($player2);
        $character3 = (new Character())->setId(30)->setName('c3')->setMain(false)->setPlayer($player3);

        $om->persist($group1);
        $om->persist($group2);
        $om->persist($player1);
        $om->persist($player2);
        $om->persist($player3);
        $om->persist($player4);
        $om->persist($corporation);
        $om->persist($character1a);
        $om->persist($character1b);
        $om->persist($character2);
        $om->persist($character3);
        $om->flush();

        self::$group2Id = $group2->getId();
        self::$p1Id = $player1->getId();
        self::$p2Id = $player2->getId();
        self::$p3Id = $player3->getId();
        self::$p4Id = $player4->getId();
    }

    public function testGetMembersWithCorporation()
    {
        $this->assertSame([], self::$repository->getMembersWithCorporation(self::$group2Id + 1));

        $this->assertSame([
            ['player_id' => self::$p1Id, 'player_name' => 'p1', 'corporation_id' => 100, 'corporation_name' => 'corp'],
            ['player_id' => self::$p2Id, 'player_name' => 'p2', 'corporation_id' => null, 'corporation_name' => null],
            ['player_id' => self::$p3Id, 'player_name' => 'p3', 'corporation_id' => null, 'corporation_name' => null],
            ['player_id' => self::$p4Id, 'player_name' => 'p4', 'corporation_id' => null, 'corporation_name' => null],
        ], self::$repository->getMembersWithCorporation(self::$group2Id));
    }
}
