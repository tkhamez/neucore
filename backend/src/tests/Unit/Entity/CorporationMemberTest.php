<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;

class CorporationMemberTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @throws \Exception
     */
    public function testJsonSerialize()
    {
        $member = new CorporationMember();
        $member->setId(123);
        $member->setName('test char');

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'locationId' => null,
            'logoffDate' => null,
            'logonDate' => null,
            'shipTypeId' => null,
            'startDate' => null,
            'character' => null,
            'player' => null,
        ], json_decode((string) json_encode($member), true));

        $member->setLocationId(234);
        $member->setLogoffDate(new \DateTime('2018-12-25 19:14:57'));
        $member->setLogonDate(new \DateTime('2018-12-25 19:14:58'));
        $member->setShipTypeId(345);
        $member->setStartDate(new \DateTime('2018-12-25 19:14:58'));
        $member->setCharacter(
            (new Character())
                ->setId(123)
                ->setName('test char')
                ->setPlayer((new Player())->setName('ply'))
        );

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'locationId' => 234,
            'logoffDate' => '2018-12-25T19:14:57Z',
            'logonDate' => '2018-12-25T19:14:58Z',
            'shipTypeId' => 345,
            'startDate' => '2018-12-25T19:14:58Z',
            'character' => [
                'id' => 123,
                'name' => 'test char',
                'main' => false,
                'lastUpdate' => null,
                'validToken' => null,
            ],
            'player' => [
                'id' => null,
                'name' => 'ply',
            ],
        ], json_decode((string) json_encode($member), true));
    }

    public function testSetGetId()
    {
        $member = new CorporationMember();
        $member->setId(123);
        $this->assertSame(123, $member->getId());
    }

    public function testSetGetName()
    {
        $member = new CorporationMember();
        $member->setName('nam');
        $this->assertSame('nam', $member->getName());
    }

    public function testSetGetLocationId()
    {
        $member = new CorporationMember();
        $member->setLocationId(11);
        $this->assertSame(11, $member->getLocationId());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLogoffDate()
    {
        $dt1 = new \DateTime('2018-12-25 19:14:57');

        $member = new CorporationMember();
        $dt2 = $member->setLogoffDate($dt1)->getLogoffDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-12-25T19:14:57+00:00', $dt2->format(\DateTime::ATOM));
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLogonDate()
    {
        $dt1 = new \DateTime('2018-12-25 19:14:58');

        $member = new CorporationMember();
        $dt2 = $member->setLogonDate($dt1)->getLogonDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-12-25T19:14:58+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetShipTypeId()
    {
        $member = new CorporationMember();
        $member->setShipTypeId(22);
        $this->assertSame(22, $member->getShipTypeId());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetStartDate()
    {
        $dt1 = new \DateTime('2018-12-25 19:14:59');

        $member = new CorporationMember();
        $dt2 = $member->setStartDate($dt1)->getStartDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-12-25T19:14:59+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetCorporation()
    {
        $member = new CorporationMember();
        $corp = new Corporation();
        $member->setCorporation($corp);
        $this->assertSame($corp, $member->getCorporation());
    }

    public function testSetGetCharacter()
    {
        $member = new CorporationMember();
        $char = new Character();
        $member->setCharacter($char);
        $this->assertSame($char, $member->getCharacter());
    }
}
