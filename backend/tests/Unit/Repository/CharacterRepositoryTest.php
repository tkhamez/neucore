<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Neucore\Entity\Character;

class CharacterRepositoryTest extends TestCase
{
    private static CharacterRepository $repository;

    private static Group $group;

    private static Player $player1;

    private static Player $player2;

    public static function setUpBeforeClass(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $om = $h->getObjectManager();

        $corporation1 = (new Corporation())->setId(100);
        $corporation2 = (new Corporation())->setId(200);
        $om->persist($corporation1);
        $om->persist($corporation2);

        $char1 = (new Character())->setId(10)->setName('char two')->setMain(true)->setCorporation($corporation1);
        $char2 = (new Character())->setId(20)->setName('char one')->setCorporation();
        $char3 = (new Character())->setId(30)->setName('three')->setMain(true);
        $char4 = (new Character())->setId(40)->setName('four')->setMain(true)->setCorporation($corporation2);

        self::$player1 = $h->addNewPlayerToCharacterAndFlush($char1);
        self::$player2 = $h->addNewPlayerToCharacterAndFlush($char2);
        $h->addNewPlayerToCharacterAndFlush($char3);
        $player4 = $h->addNewPlayerToCharacterAndFlush($char4);

        $h->addCharacterToPlayer('char two alt', 101, self::$player1);

        self::$group = (new Group())->setName('g1');
        $om->persist(self::$group);

        self::$player1->addGroup(self::$group);
        self::$player2->addGroup(self::$group);
        $player4->addGroup(self::$group);

        $om->flush();

        self::$repository = (new RepositoryFactory($om))->getCharacterRepository();
    }

    public function testFindMainByNamePartialMatch()
    {
        $actual = self::$repository->findMainByNamePartialMatch('har');
        $this->assertSame(1, count($actual));
        $this->assertSame('char two', $actual[0]->getName());
        $this->assertSame(10, $actual[0]->getID());
    }

    public function testGetGroupMembersMainCharacter()
    {
        $actual1 = self::$repository->getGroupMembersMainCharacter(self::$group->getId());
        $actual2 = self::$repository->getGroupMembersMainCharacter(self::$group->getId(), 100);

        $this->assertSame([10, 40], $actual1);
        $this->assertSame([10], $actual2);
    }

    public function testGetAllCharactersFromPlayers()
    {
        $this->assertSame([
            ['id' => 10, 'playerId' => self::$player1->getId()],
            ['id' => 101, 'playerId' => self::$player1->getId()],
            ['id' => 20, 'playerId' => self::$player2->getId()],
        ], self::$repository->getAllCharactersFromPlayers([self::$player1->getId(), self::$player2->getId()]));
    }

    public function testGetCharacterIdsFromPlayers()
    {
        $this->assertSame(
            [10, 20, 101],
            self::$repository->getCharacterIdsFromPlayers([self::$player1->getId(), self::$player2->getId()])
        );
    }
}
