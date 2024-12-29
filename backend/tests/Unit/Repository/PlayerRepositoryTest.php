<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Alliance;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Util\Database;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class PlayerRepositoryTest extends TestCase
{
    private static EntityManagerInterface $em;

    private static Group $group1;

    private static Group $group2;

    private static Player$player1;

    private static Player$player4;

    private static Player $player6;

    private PlayerRepository $repo;

    public static function setupBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        self::$em = $helper->getEm();

        self::$group1 = (new Group())->setName('g1');
        self::$group2 = (new Group())->setName('g2');
        self::$em->persist(self::$group1);
        self::$em->persist(self::$group2);

        $roleTracking = (new Role(10))->setName(Role::TRACKING);
        self::$em->persist($roleTracking);

        $alliance = (new Alliance())->setId(1000)->setName('alli1');
        $corp1 = (new Corporation())->setId(98000101)->setName('corp1')->setAlliance($alliance);
        $corp2 = (new Corporation())->setId(98000102)->setName('corp2');
        $corp3 = (new Corporation())->setId(98000103)->setName('corp3')->setAlliance($alliance);
        $corp4 = (new Corporation())->setId(1000500)->setName('corp4'); // NPC corp
        self::$em->persist($alliance);
        self::$em->persist($corp1);
        self::$em->persist($corp2);
        self::$em->persist($corp3);
        self::$em->persist($corp4);

        self::$player1 = $helper->addCharacterMain('c1', 1)->getPlayer();
        self::$player1->getCharacters()[0]->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(true);
        self::$player1->getCharacters()[0]->setCorporation($corp1)->setCreated(new \DateTime());
        $char1b = $helper->addCharacterToPlayer('c1b 12\ 12_ 12% 12', 12, self::$player1, true)
            ->setCorporation($corp2);
        $char1b->setCorporation($corp1);
        $char1b->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false);
        $char1c = $helper->addCharacterToPlayer('c1c', 1313, self::$player1, true)->setCorporation($corp4);
        $char1c->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false);
        self::$player1->addCharacter($char1b);
        self::$player1->addCharacter($char1c);
        self::$em->persist($char1b);
        self::$em->persist($char1c);

        $player2 = $helper->addCharacterMain('c2', 2)->getPlayer();
        $player2->addGroup(self::$group2);
        $player2->addRole($roleTracking);
        $player2->getCharacters()[0]->setCorporation($corp2);

        $player3 = (new Player())->setName('p3');
        $player3->addGroup(self::$group1);
        $player3->addGroup(self::$group2);

        self::$player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
        self::$player4->getCharacters()[0]->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(true);
        self::$player4->addRole($roleTracking);
        self::$player4->getCharacters()[0]->setCorporation($corp3)->setCreated(new \DateTime('now -7 days'));

        $player5 = (new Player())->setName('p5');

        self::$player6 = $helper->addCharacterMain('c6', 6)->getPlayer();
        self::$player6->getCharacters()[0]->setCorporation($corp4)
            ->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(true);

        self::$player1->setStatus(Player::STATUS_MANAGED);
        $player3->setStatus(Player::STATUS_MANAGED);
        self::$player4->setStatus(Player::STATUS_MANAGED);

        self::$em->persist($player3);
        self::$em->persist($player5);

        // additional data for search by name
        $moveOutChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-2-1')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_MOVED)
            ->setPlayer(self::$player4)
            ->setNewPlayer(self::$player6);
        $moveBackChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-2-1')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_MOVED)
            ->setPlayer(self::$player6)
            ->setNewPlayer(self::$player4);
        $deletedChar1 = (new RemovedCharacter())
            ->setCharacterId(21)
            ->setCharacterName('removed-2-1')
            ->setRemovedDate(new \DateTime())
            ->setReason(RemovedCharacter::REASON_DELETED_MANUALLY)
            ->setPlayer(self::$player4);
        $removedChar2 = (new RemovedCharacter())
            ->setCharacterId(31)
            ->setCharacterName('removed-3-1')
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
        self::$em->persist($moveOutChar1);
        self::$em->persist($moveBackChar1);
        self::$em->persist($deletedChar1);
        self::$em->persist($removedChar2);
        self::$em->persist($renamed1);
        self::$em->persist($renamed2);

        self::$em->flush();
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
        $this->assertSame('c1b 12\ 12_ 12% 12', $actual[0]->getCharacters()[1]->getName());
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

    public function testFindInAlliances()
    {
        $actual = $this->repo->findInAlliances([1000]);
        $this->assertSame([self::$player1->getId(), self::$player4->getId()], $actual);
    }

    public function testFindInCorporations()
    {
        $actual = $this->repo->findInCorporations([98000101, 98000103]);
        $this->assertSame([self::$player1->getId(), self::$player4->getId()], $actual);
    }

    public function testFindInCorporation()
    {
        // There are 2 characters in this corporation from the same account
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
            [self::$player4, self::$player6],
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
            [self::$player4],
        );

        $this->assertSame(1, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
    }

    public function testFindCharacters_SpecialChars()
    {
        $this->assertSame([], $this->repo->findCharacters('c1_', false));
        $this->assertSame([], $this->repo->findCharacters('c1%', false));
        $this->assertSame(1, count($this->repo->findCharacters('12_ 12', false)));
        $this->assertSame(1, count($this->repo->findCharacters('12% 12', false)));
        $this->assertSame(1, count($this->repo->findCharacters('12\ 12', false)));
    }

    public function testFindCharacters_byName()
    {
        $actual = $this->repo->findCharacters('1', false);

        $this->assertSame(8, count($actual));
        $this->assertSame([ // existing char
            'characterId' => 1,
            'characterName' => 'c1',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[0]->jsonSerialize());
        $this->assertSame([ // existing char
            'characterId' => 12,
            'characterName' => 'c1b 12\ 12_ 12% 12',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[1]->jsonSerialize());
        $this->assertSame([ // existing char
            'characterId' => 1313,
            'characterName' => 'c1c',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[2]->jsonSerialize());
        $this->assertSame([ // renamed character
            'characterId' => 6,
            'characterName' => 'c6-41',
            'playerId' => self::$player6->getId(),
            'playerName' => 'c6',
        ], $actual[3]->jsonSerialize());
        $this->assertSame([ // renamed character
            'characterId' => 6,
            'characterName' => 'c6-51',
            'playerId' => self::$player6->getId(),
            'playerName' => 'c6',
        ], $actual[4]->jsonSerialize());
        $this->assertSame([ // moved out/deleted character - there are two entries, filtered by "distinct"
            'characterId' => 21,
            'characterName' => 'removed-2-1',
            'playerId' => self::$player4->getId(),
            'playerName' => 'c4',
        ], $actual[5]->jsonSerialize());
        $this->assertSame([ // moved back character
            'characterId' => 21,
            'characterName' => 'removed-2-1',
            'playerId' => self::$player6->getId(),
            'playerName' => 'c6',
        ], $actual[6]->jsonSerialize());
        $this->assertSame([ // removed character
            'characterId' => 31,
            'characterName' => 'removed-3-1',
            'playerId' => self::$player4->getId(),
            'playerName' => 'c4',
        ], $actual[7]->jsonSerialize());
    }

    public function testFindCharacters_CurrentOnly()
    {
        $actual = $this->repo->findCharacters('1', true);

        $this->assertSame(3, count($actual));
        $this->assertSame([ // existing char
            'characterId' => 1,
            'characterName' => 'c1',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[0]->jsonSerialize());
        $this->assertSame([ // existing char
            'characterId' => 12,
            'characterName' => 'c1b 12\ 12_ 12% 12',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[1]->jsonSerialize());
        $this->assertSame([ // existing char
            'characterId' => 1313,
            'characterName' => 'c1c',
            'playerId' => self::$player1->getId(),
            'playerName' => 'c1',
        ], $actual[2]->jsonSerialize());
    }

    public function testFindCharacters_byId()
    {
        $this->assertSame([], $this->repo->findCharacters('13', false));

        $result = $this->repo->findCharacters('1313', false);
        $this->assertSame(1, count($result));
        $this->assertSame(1313, $result[0]->characterId);
        $this->assertSame('c1c', $result[0]->characterName);
        $this->assertSame(self::$player1->getId(), $result[0]->playerId);
        $this->assertSame('c1', $result[0]->playerName);

        $this->assertSame([], $this->repo->findCharacters('21', true));

        $result2 = $this->repo->findCharacters('31', false);
        $this->assertSame(1, count($result2));
        $this->assertSame([
            'characterId' => 31,
            'characterName' => 'removed-3-1',
            'playerId' => self::$player4->getId(),
            'playerName' => 'c4',
        ], $result2[0]->jsonSerialize());
    }

    public function testFindPlayersOfCharacters()
    {
        $this->assertSame(
            [self::$player1->getId(), self::$player4->getId()],
            $this->repo->findPlayersOfCharacters([1, 12, 3]),
        );
    }

    public function testFindPlayersOfRecentlyAddedCharacters()
    {
        $actual = $this->repo->findPlayersOfRecentlyAddedCharacters(30);
        $this->assertSame([self::$player1->getId(), self::$player4->getId()], $actual);
    }
}
