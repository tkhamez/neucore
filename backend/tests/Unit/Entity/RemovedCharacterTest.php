<?php

/** @noinspection DuplicatedCode */

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
            'newPlayerId' => 0,
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

    public function testToCoreMovedCharacter()
    {
        $this->assertNull((new RemovedCharacter())->toCoreMovedCharacter());

        $rm = (new RemovedCharacter())
            ->setPlayer((new Player())->setId(1)->setName('p'))
            ->setNewPlayer((new Player())->setId(2)->setName('q'))
            ->setCharacterId(10)
            ->setCharacterName('c')
            ->setRemovedDate(new \DateTime())
            ->setReason('reason')
            ->setDeletedBy((new Player())->setId(3)->setName('r'))
        ;
        $crm = $rm->toCoreMovedCharacter();
        $this->assertSame(1, $crm->oldPlayer->playerId);
        $this->assertSame('p', $crm->oldPlayer->playerName);
        $this->assertSame(2, $crm->newPlayer->playerId);
        $this->assertSame('q', $crm->newPlayer->playerName);
        $this->assertSame(10, $crm->character->id);
        $this->assertSame(0, $crm->character->playerId);
        $this->assertSame('c', $crm->character->name);
        $this->assertNull($crm->character->playerName);
        $this->assertInstanceOf(\DateTime::class, $crm->date);
        $this->assertSame('reason', $crm->reason);
        $this->assertSame(3, $crm->deletedBy->playerId);
        $this->assertSame('r', $crm->deletedBy->playerName);

        $rm2 = (new RemovedCharacter())
            ->setPlayer((new Player())->setId(1)->setName('p'))
            ->setCharacterId(10)
            ->setCharacterName('c')
            ->setRemovedDate(new \DateTime())
            ->setReason('reason')
        ;
        $crm2 = $rm2->toCoreMovedCharacter();
        $this->assertSame(1, $crm2->oldPlayer->playerId);
        $this->assertSame('p', $crm2->oldPlayer->playerName);
        $this->assertNull($crm2->newPlayer);
        $this->assertSame(10, $crm2->character->id);
        $this->assertSame(0, $crm2->character->playerId);
        $this->assertSame('c', $crm2->character->name);
        $this->assertNull($crm2->character->playerName);
        $this->assertInstanceOf(\DateTime::class, $crm2->date);
        $this->assertSame('reason', $crm2->reason);
        $this->assertNull($crm2->deletedBy);
    }
}
