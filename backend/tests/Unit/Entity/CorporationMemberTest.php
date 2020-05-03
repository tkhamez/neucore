<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Api;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\Player;
use PHPUnit\Framework\TestCase;

class CorporationMemberTest extends TestCase
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
            'location' => null,
            'logoffDate' => null,
            'logonDate' => null,
            'shipType' => null,
            'startDate' => null,
            'missingCharacterMailSentDate' => null,
            'missingCharacterMailSentResult' => null,
            'character' => null,
            'player' => null,
        ], json_decode((string) json_encode($member), true));

        $member->setLocation((new EsiLocation())->setId(234));
        $member->setLogoffDate(new \DateTime('2018-12-25 19:14:57'));
        $member->setLogonDate(new \DateTime('2018-12-25 19:14:58'));
        $member->setShipType((new EsiType())->setId(345));
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
            'location' => ['id' => 234, 'name' => null, 'category' => null],
            'logoffDate' => '2018-12-25T19:14:57Z',
            'logonDate' => '2018-12-25T19:14:58Z',
            'shipType' => ['id' => 345, 'name' => null],
            'startDate' => '2018-12-25T19:14:58Z',
            'missingCharacterMailSentDate' => null,
            'missingCharacterMailSentResult' => null,
            'character' => [
                'id' => 123,
                'name' => 'test char',
                'main' => false,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
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

    public function testSetGetLocation()
    {
        $member = new CorporationMember();
        $location = new EsiLocation();

        $member->setLocation($location);
        $this->assertSame($location, $member->getLocation());

        $member->setLocation(null);
        $this->assertNull($member->getLocation());
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

    public function testSetGetShipType()
    {
        $shipType = new EsiType();
        $member = new CorporationMember();
        $member->setShipType($shipType);
        $this->assertSame($shipType, $member->getShipType());
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

    /**
     * @throws \Exception
     */
    public function testSetGetMissingCharacterMailSentDate()
    {
        $dt1 = new \DateTime('2018-12-25 19:14:59');

        $member = new CorporationMember();
        $dt2 = $member->setMissingCharacterMailSentDate($dt1)->getMissingCharacterMailSentDate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-12-25T19:14:59+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetMissingCharacterMailSentResult()
    {
        $member = new CorporationMember();

        $result = $member->setMissingCharacterMailSentResult(Api::MAIL_OK);
        $this->assertSame($member, $result);
        $this->assertSame(Api::MAIL_OK, $member->getMissingCharacterMailSentResult());

        $member->setMissingCharacterMailSentResult(null);
        $this->assertNull($member->getMissingCharacterMailSentResult());
    }
}
