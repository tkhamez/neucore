<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class PlayerRepositoryTest extends TestCase
{
    /**
     * @var PlayerRepository
     */
    private $repo;

    public static function setupBeforeClass()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        $player1 = $helper->addCharacterMain('c1', 1)->getPlayer();
        $player1->getCharacters()[0]->setValidToken(true);
        $char1a = (new Character())->setId(11)->setName('c1a')->setValidToken(false);
        $char1a->setPlayer($player1);
        $player1->addCharacter($char1a);
        $em->persist($char1a);

        $helper->addCharacterMain('c2', 2)->getPlayer();

        $player3 = (new Player())->setName('p3');

        $player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
        $player4->getCharacters()[0]->setValidToken(true);

        $player5 = (new Player())->setName('p5');

        $player1->setStatus(Player::STATUS_MANAGED);
        $player3->setStatus(Player::STATUS_MANAGED);
        $player4->setStatus(Player::STATUS_MANAGED);

        $em->persist($player3);
        $em->persist($player5);

        $em->flush();
    }

    protected function setup()
    {
        $em = (new Helper())->getEm();
        $this->repo = (new RepositoryFactory($em))->getPlayerRepository();
    }

    public function testFindWithCharacters()
    {
        $actual = $this->repo->findWithCharacters();

        $this->assertSame(3, count($actual));
        $this->assertSame('c1', $actual[0]->getName());
        $this->assertSame('c1', $actual[0]->getCharacters()[0]->getName());
        $this->assertSame('c1a', $actual[0]->getCharacters()[1]->getName());
        $this->assertSame('c2', $actual[1]->getName());
        $this->assertSame('c4', $actual[2]->getName());
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
}
