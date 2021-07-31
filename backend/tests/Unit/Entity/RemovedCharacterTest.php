<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use PHPUnit\Framework\TestCase;

class RemovedCharacterTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testJsonSerialize()
    {
        $char = new RemovedCharacter();
        $char->setPlayer((new Player())->setName('Old Player'));
        $char->setCharacterId(123);
        $char->setCharacterName('test char');
        $char->setRemovedDate(new \DateTime('2018-04-26 18:59:35'));
        $char->setReason(RemovedCharacter::REASON_MOVED);
        $char->setNewPlayer((new Player())->setName('New Player'));
        $char->setDeletedBy((new Player())->setName('Deleted By'));

        $this->assertSame([
            'player' => ['id' => null, 'name' => 'Old Player'],
            'characterId' => 123,
            'characterName' => 'test char',
            'removedDate' => '2018-04-26T18:59:35Z',
            'reason' => RemovedCharacter::REASON_MOVED,
            'deletedBy' => ['id' => null, 'name' => 'Deleted By'],
            'newPlayerId' => null,
            'newPlayerName' => 'New Player',
        ], json_decode((string) json_encode($char), true));
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
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }

    public function testSetGetReason()
    {
        $rm = new RemovedCharacter();
        $retVal = $rm->setReason('nam');
        $this->assertSame($rm, $retVal);
        $this->assertSame('nam', $rm->getReason());
    }

    public function testSetGetDeletedBy()
    {
        $rm = new RemovedCharacter();
        $player = new Player();
        $retVal = $rm->setDeletedBy($player);
        $this->assertSame($rm, $retVal);
        $this->assertSame($player, $rm->getDeletedBy());
    }
}
