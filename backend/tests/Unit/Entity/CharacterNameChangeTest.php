<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Character;
use Neucore\Entity\CharacterNameChange;
use PHPUnit\Framework\TestCase;

class CharacterNameChangeTest extends TestCase
{
    public function testJsonSerialize()
    {
        $cnc = (new CharacterNameChange())
            ->setCharacter((new Character())->setName('char name'))
            ->setOldName('old char name')
            ->setChangeDate(new \DateTime('2031-03-27 16:52:57'));

        $this->assertSame(
            ['oldName' => 'old char name', 'changeDate' => '2031-03-27T16:52:57Z'],
            $cnc->jsonSerialize(),
        );
    }

    public function testGetId()
    {
        $this->assertSame(0, (new CharacterNameChange())->getId());
    }

    public function testSetGetCharacter()
    {
        $cnc = new CharacterNameChange();
        $character = new Character();
        $cnc->setCharacter($character);
        $this->assertSame($character, $cnc->getCharacter());
    }

    public function testSetGetName()
    {
        $cnc = new CharacterNameChange();
        $cnc->setOldName('old name');
        $this->assertSame('old name', $cnc->getOldName());
    }

    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2031-03-27 16:52:57');

        $cnc = new CharacterNameChange();
        $cnc->setChangeDate($dt1);
        $dt2 = $cnc->getChangeDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2031-03-27T16:52:57+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }
}
