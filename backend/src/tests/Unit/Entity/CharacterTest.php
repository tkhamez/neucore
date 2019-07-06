<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Character;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;
use Neucore\Entity\Corporation;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
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
            'lastUpdate' => null,
            'validToken' => null,
            'corporation' => null
        ], json_decode((string) json_encode($char), true));

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
            'lastUpdate' => null,
            'validToken' => null
        ], $char->jsonSerialize(false));
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
        $this->assertFalse($char->getMain());
        $char->setMain(true);
        $this->assertTrue($char->getMain());
    }

    public function testSetGetPlayer()
    {
        $char = new Character();
        $player = new Player();
        $char->setPlayer($player);
        $this->assertSame($player, $char->getPlayer());
    }

    public function testSetGetCorporation()
    {
        $char = new Character();
        $corp = new Corporation();
        $char->setCorporation($corp);
        $this->assertSame($corp, $char->getCorporation());
    }

    public function testSetGetCorporationMember()
    {
        $char = new Character();
        $member = new CorporationMember();
        $char->setCorporationMember($member);
        $this->assertSame($member, $char->getCorporationMember());
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

    public function testSetGetValidToken()
    {
        $char = new Character();

        $this->assertNull($char->getValidToken());
        $this->assertTrue($char->setValidToken(true)->getValidToken());
        $this->assertFalse($char->setValidToken(false)->getValidToken());
        $this->assertNull($char->setValidToken(null)->getValidToken());
    }

    public function testSetValidTokenUpdatesTime()
    {
        $char = new Character();

        $this->assertNull($char->getValidTokenTime());
        $this->assertNull($char->getValidToken());

        $char->setValidToken(null);
        $this->assertNull($char->getValidTokenTime());

        $char->setValidToken(false);
        $time1 = $char->getValidTokenTime();
        $this->assertNotNull($time1);

        $char->setValidToken(true);
        $time2 = $char->getValidTokenTime();
        $this->assertNotSame($time1, $time2);
        $this->assertNotNull($time2);

        $char->setValidToken(null);
        $time3 = $char->getValidTokenTime();
        $this->assertNotSame($time2, $time3);
        $this->assertNotNull($char->getValidTokenTime());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetValidTokenTime()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:35');

        $char = new Character();
        $char->setValidTokenTime($dt1);
        $dt2 = $char->getValidTokenTime();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetScopes()
    {
        $char = new Character();
        $char->setScopes('esi.one esi.two');
        $this->assertSame('esi.one esi.two', $char->getScopes());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetCreated()
    {
        $dt1 = new \DateTime('2019-07-06 14:56:52');

        $char = new Character();
        $this->assertNull($char->getCreated());

        $char->setCreated($dt1);
        $dt2 = $char->getCreated();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2019-07-06T14:56:52+00:00', $dt2->format(\DateTime::ATOM));
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastLogin()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:35');

        $char = new Character();
        $char->setLastLogin($dt1);
        $dt2 = $char->getLastLogin();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTime::ATOM));
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:36');

        $char = new Character();
        $char->setLastUpdate($dt1);
        $dt2 = $char->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:36+00:00', $dt2->format(\DateTime::ATOM));
    }
}
