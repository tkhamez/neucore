<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Character;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class PlayerRepositoryTest extends TestCase
{
    /**
     * @var Group
     */
    private static $group1;
    
    /**
     * @var Group
     */
    private static $group2;

    /**
     * @var Player
     */
    private static $player1;

    /**
     * @var Player
     */
    private static $player4;

    /**
     * @var Player
     */
    private static $player6;

    /**
     * @var PlayerRepository
     */
    private $repo;

    public static function setupBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        self::$group1 = (new Group())->setName('g1');
        self::$group2 = (new Group())->setName('g2');
        $om->persist(self::$group1);
        $om->persist(self::$group2);

        $roleTracking = (new Role(10))->setName(Role::TRACKING);
        $om->persist($roleTracking);

        $corp1 = (new Corporation())->setId(98000101)->setName('corp1');
        $corp2 = (new Corporation())->setId(98000102)->setName('corp2');
        $corp3 = (new Corporation())->setId(98000103)->setName('corp3');
        $corp4 = (new Corporation())->setId(1000500)->setName('corp4'); // NPC corp
        $om->persist($corp1);
        $om->persist($corp2);
        $om->persist($corp3);
        $om->persist($corp4);

        self::$player1 = $helper->addCharacterMain('c1', 1)->getPlayer();
        self::$player1->getCharacters()[0]->setValidToken(true);
        self::$player1->getCharacters()[0]->setCorporation($corp1);
        $char1b = (new Character())->setId(12)->setName('c1b')->setValidToken(false);
        $char1b->setPlayer(self::$player1);
        $char1b->setCorporation($corp2);
        $char1c = (new Character())->setId(1313)->setName('c1c')->setValidToken(false);
        $char1c->setPlayer(self::$player1);
        $char1c->setCorporation($corp4);
        self::$player1->addCharacter($char1b);
        self::$player1->addCharacter($char1c);
        $om->persist($char1b);
        $om->persist($char1c);

        $player2 = $helper->addCharacterMain('c2', 2)->getPlayer();
        $player2->addGroup(self::$group2);
        $player2->addRole($roleTracking);
        $player2->getCharacters()[0]->setCorporation($corp2);

        $player3 = (new Player())->setName('p3');
        $player3->addGroup(self::$group1);
        $player3->addGroup(self::$group2);

        self::$player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
        self::$player4->getCharacters()[0]->setValidToken(true);
        self::$player4->addRole($roleTracking);
        self::$player4->getCharacters()[0]->setCorporation($corp3);

        $player5 = (new Player())->setName('p5');

        self::$player6 = $helper->addCharacterMain('c6', 6)->getPlayer();
        self::$player6->getCharacters()[0]->setCorporation($corp4)->setValidToken(true);

        self::$player1->setStatus(Player::STATUS_MANAGED);
        $player3->setStatus(Player::STATUS_MANAGED);
        self::$player4->setStatus(Player::STATUS_MANAGED);

        $om->persist($player3);
        $om->persist($player5);

        // additional data for search by name
        $moveOutChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-21')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_MOVED)
            ->setPlayer(self::$player4)
            ->setNewPlayer(self::$player6);
        $moveBackChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-21')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_MOVED)
            ->setPlayer(self::$player6)
            ->setNewPlayer(self::$player4);
        $deletedChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-21')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_DELETED_MANUALLY)
            ->setPlayer(self::$player4);
        $removedChar2 = (new RemovedCharacter())
            ->setCharacterId(31)
            ->setCharacterName('removed-31')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_DELETED_MANUALLY)
            ->setPlayer(self::$player4);
        $renamed1 = (new CharacterNameChange())
            ->setCharacter(self::$player6->getCharacters()[0])
            ->setOldName('c6-41')
            ->setChangeDate(new \DateTime());
        $renamed2 = (new CharacterNameChange())
            ->setCharacter(self::$player6->getCharacters()[0])
            ->setOldName('c6-51')
            ->setChangeDate(new \DateTime());
        $om->persist($moveOutChar1);
        $om->persist($moveBackChar1);
        $om->persist($deletedChar1);
        $om->persist($removedChar2);
        $om->persist($renamed1);
        $om->persist($renamed2);

        $om->flush();
    }

    protected function setup(): void
    {
        $this->repo = (new RepositoryFactory((new Helper())->getObjectManager()))->getPlayerRepository();
    }

    public function testFindWithCharacters()
    {
        $actual = $this->repo->findWithCharacters();

        $this->assertSame(4, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c1', $actual[0]->getCharacters()[0]->getName());
        $this->assertSame('c1b', $actual[0]->getCharacters()[1]->getName());
        $this->assertSame('c2', $actual[1]->getName());
        $this->assertSame('c4', $actual[2]->getName());
        $this->assertSame('c6', $actual[3]->getName());
    }

    public function testFindWithoutCharacters()
    {
        $actual = $this->repo->findWithoutCharacters();

        $this->assertSame(2, count($actual));
        $this->assertSame('p3', $actual[0]->getName());
        $this->assertSame('p5', $actual[1]->getName());
    }

    public function testFindWithCharactersAndStatus()
    {
        $actual = $this->repo->findWithCharactersAndStatus(Player::STATUS_MANAGED);

        $this->assertSame(2, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c4', $actual[1]->getName());
    }

    public function testFindWithInvalidToken()
    {
        $actual = $this->repo->findWithInvalidToken();

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindWithNoToken()
    {
        $actual = $this->repo->findWithNoToken();

        $this->assertSame(1, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
    }

    public function testFindInCorporation()
    {
        $actual = $this->repo->findInCorporation(98000101);

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindWithGroups()
    {
        $actual = $this->repo->findWithGroups([self::$group1->getId(), self::$group2->getId()]);
        
        $this->assertSame(2, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
        $this->assertSame('p3', $actual[1]->getName());
    }

    public function testFindWithRole()
    {
        $actual = $this->repo->findWithRole(10);
        
        $this->assertSame(2, count($actual));
        $this->assertSame('c2', $actual[0]->getName());
        $this->assertSame('c4', $actual[1]->getName());
    }

    public function testFindInCorporationsWithExcludes()
    {
        // player c1 is in corp 98000101, 98000102, 1000500
        // player c2 is in corp 98000102
        // player c4 is in corp 98000103
        // player 3, 5 have no character/corp
        // player 6 is in corp 1000500

        $actual = $this->repo->findInCorporationsWithExcludes(
            [98000101, 98000102, 98000103, 1000500],
            [self::$player4, self::$player6]
        );

        $this->assertSame(2, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c2', $actual[1]->getName());
    }

    public function testFindNotInNpcCorporationsWithExcludes()
    {
        // player c1 is in corp 98000101, 98000102, 1000500
        // player c2 is in corp 98000102
        // player c4 is in corp 98000103
        // player 3, 5 have no character/corp
        // player 6 is in corp 1000500

        $actual = $this->repo->findNotInNpcCorporationsWithExcludes(
            [98000102],
            [self::$player4]
        );

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindCharacters_byName()
    {
        $actual = $this->repo->findCharacters('1', false);

        $this->assertSame(8, count($actual));
        $this->assertSame([ // existing char
            'character_id' => 1,
            'character_name' => 'c1',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[0]);
        $this->assertSame([ // existing char
            'character_id' => 12,
            'character_name' => 'c1b',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[1]);
        $this->assertSame([ // existing char
            'character_id' => 1313,
            'character_name' => 'c1c',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[2]);
        $this->assertSame([ // renamed character
            'character_id' => 6,
            'character_name' => 'c6-41',
            'player_id' => self::$player6->getId(),
            'player_name' => 'c6',
        ], $actual[3]);
        $this->assertSame([ // renamed character
            'character_id' => 6,
            'character_name' => 'c6-51',
            'player_id' => self::$player6->getId(),
            'player_name' => 'c6',
        ], $actual[4]);
        $this->assertSame([ // moved out/deleted character - there are two entries, filtered by "distinct"
            'character_id' => 21,
            'character_name' => 'removed-21',
            'player_id' => self::$player4->getId(),
            'player_name' => 'c4',
        ], $actual[5]);
        $this->assertSame([ // moved back character
            'character_id' => 21,
            'character_name' => 'removed-21',
            'player_id' => self::$player6->getId(),
            'player_name' => 'c6',
        ], $actual[6]);
        $this->assertSame([ // removed character
            'character_id' => 31,
            'character_name' => 'removed-31',
            'player_id' => self::$player4->getId(),
            'player_name' => 'c4',
        ], $actual[7]);
    }

    public function testFindCharacters_CurrentOnly()
    {
        $actual = $this->repo->findCharacters('1', true);

        $this->assertSame(3, count($actual));
        $this->assertSame([ // existing char
            'character_id' => 1,
            'character_name' => 'c1',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[0]);
        $this->assertSame([ // existing char
            'character_id' => 12,
            'character_name' => 'c1b',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[1]);
        $this->assertSame([ // existing char
            'character_id' => 1313,
            'character_name' => 'c1c',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ], $actual[2]);
    }

    public function testFindCharacters_byId()
    {
        $this->assertSame([], $this->repo->findCharacters('13', false));

        $this->assertSame([[
            'character_id' => 1313,
            'character_name' => 'c1c',
            'player_id' => self::$player1->getId(),
            'player_name' => 'c1',
        ]], $this->repo->findCharacters('1313', false));
    }

    public function testFindPlayersOfCharacters()
    {
        $this->assertSame(
            [self::$player1->getId(), self::$player4->getId()],
            $this->repo->findPlayersOfCharacters([1, 12, 3])
        );
    }
}
