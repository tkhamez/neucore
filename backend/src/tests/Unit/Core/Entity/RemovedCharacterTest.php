<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Player;
use Brave\Core\Entity\RemovedCharacter;
use PHPUnit\Framework\TestCase;

class RemovedCharacterTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testJsonSerialize()
    {
        $char = new RemovedCharacter();
        $char->setCharacterId(123);
        $char->setCharacterName('test char');
        $char->setRemovedDate(new \DateTime('2018-04-26 18:59:35'));
        $char->setReason(RemovedCharacter::REASON_MOVED);
        $char->setNewPlayer((new Player())->setName('New Player'));

        $this->assertSame([
            'characterId' => 123,
            'characterName' => 'test char',
            'removedDate' => '2018-04-26T18:59:35Z',
            'reason' => RemovedCharacter::REASON_MOVED,
            'newPlayerId' => null,
            'newPlayerName' => 'New Player',
        ], json_decode(json_encode($char), true));
    }

    public function testGetId()
    {
        $this->assertNull((new RemovedCharacter())->getId());
    }

    public function testSetGetNewPlayer()
    {
        $rm = new RemovedCharacter();
        $player = new Player();
        $retVal = $rm->setNewPlayer($player);
        $this->assertSame($rm, $retVal);
        $this->assertSame($player, $rm->getNewPlayer());
    }

    public function testSetGetPlayer()
    {
        $rm = new RemovedCharacter();
        $player = new Player();
        $rm->setPlayer($player);
        $this->assertSame($player, $rm->getPlayer());
    }

    public function testSetGetId()
    {
        $rm = new RemovedCharacter();
        $rm->setCharacterId(123);
        $this->assertSame(123, $rm->getCharacterId());
    }

    public function testSetGetCharacterName()
    {
        $rm = new RemovedCharacter();
        $rm->setCharacterName('nam');
        $this->assertSame('nam', $rm->getCharacterName());
    }

    public function testSetGetAction()
    {
        $rm = new RemovedCharacter();
        $retVal = $rm->setReason('nam');
        $this->assertSame($rm, $retVal);
        $this->assertSame('nam', $rm->getReason());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetRemovedDate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:35');

        $rm = new RemovedCharacter();
        $rm->setRemovedDate($dt1);
        $dt2 = $rm->getRemovedDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTime::ATOM));
    }
}
