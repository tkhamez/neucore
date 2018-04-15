<?php
namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;

class CharacterTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $char = new Character();
        $char->setId(123);
        $char->setName('test char');
        $char->setMain(false);

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
        ], json_decode(json_encode($char), true));
    }

    public function testSetGetId()
    {
        $char = new Character();
        $char->setId(123);
        $this->assertSame(123, $char->getId());
    }

    public function testSetGetName()
    {
        $char = new Character();
        $char->setName('nam');
        $this->assertSame('nam', $char->getName());
    }

    public function testSetGetMain()
    {
        $char = new Character();
        $char->setMain(true);
        $this->assertSame(true, $char->getMain());
    }

    public function testSetGetPlayer()
    {
        $char = new Character();
        $player = new Player();
        $char->setPlayer($player);
        $this->assertSame($player, $char->getPlayer());
    }

    public function testSetGetCharacterOwnerHash()
    {
        $char = new Character();
        $char->setCharacterOwnerHash('abc');
        $this->assertSame('abc', $char->getCharacterOwnerHash());
    }

    public function testSetGetAccessToken()
    {
        $char = new Character();
        $char->setAccessToken('123');
        $this->assertSame('123', $char->getAccessToken());
    }

    public function testSetGetExpires()
    {
        $char = new Character();
        $char->setExpires(456);
        $this->assertSame(456, $char->getExpires());
    }

    public function testSetGetRefreshToken()
    {
        $char = new Character();
        $char->setRefreshToken('dfg');
        $this->assertSame('dfg', $char->getRefreshToken());
    }
}
