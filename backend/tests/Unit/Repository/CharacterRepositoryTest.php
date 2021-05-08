<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Neucore\Entity\Character;

class CharacterRepositoryTest extends TestCase
{
    /**
     * @var CharacterRepository
     */
    private static $repository;

    /**
     * @var Group
     */
    private static $group;

    /**
     * @var Player
     */
    private static $player1;

    /**
     * @var Player
     */
    private static $player2;

    public static function setUpBeforeClass(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $om = $h->getObjectManager();

        $char1 = (new Character())->setId(10)->setName('char two')->setMain(true);
        $char2 = (new Character())->setId(20)->setName('char one');
        $char3 = (new Character())->setId(30)->setName('three')->setMain(true);

        self::$player1 = $h->addNewPlayerToCharacterAndFlush($char1);
        self::$player2 = $h->addNewPlayerToCharacterAndFlush($char2);
        $h->addNewPlayerToCharacterAndFlush($char3);

        $h->addCharacterToPlayer('char two alt', 101, self::$player1);

        self::$group = (new Group())->setName('g1');
        $om->persist(self::$group);

        self::$player1->addGroup(self::$group);
        self::$player2->addGroup(self::$group);

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
        $actual = self::$repository->getGroupMembersMainCharacter(self::$group->getId());

        $this->assertSame([10], $actual);
    }

    public function testGetAllCharactersFromPlayers()
    {
        $this->assertSame([
            ['id' => 10, 'playerId' => self::$player1->getId()],
            ['id' => 101, 'playerId' => self::$player1->getId()],
            ['id' => 20, 'playerId' => self::$player2->getId()],
        ], self::$repository->getAllCharactersFromPlayers([self::$player1->getId(), self::$player2->getId()]));
    }
}
