<?php declare(strict_types=1);

namespace Tests\Unit\Repository;

use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\PlayerRepository;
use Tests\Helper;

class PlayerRepositoryTest extends \PHPUnit\Framework\TestCase
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
        $helper->addCharacterMain('c2', 2)->getPlayer();
        $player3 = (new Player())->setName('p3');
        $player4 = $helper->addCharacterMain('c4', 3)->getPlayer();
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
}
