<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Api;
use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use PHPUnit\Framework\TestCase;

class EsiTokenTest extends TestCase
{
    public function testJsonSerialize()
    {
        $type = new EsiToken();
        $type->setValidToken(true);

        $this->assertSame([
            'eveLoginId' => 0,
            'validToken' => true,
            'validTokenTime' => $type->getValidTokenTime()->format(Api::DATE_FORMAT),
            'hasRoles' => null,
        ], json_decode((string) json_encode($type), true));

        $type->setEveLogin((new EveLogin())->setId(1));
        $this->assertSame([
            'eveLoginId' => 1,
            'validToken' => true,
            'validTokenTime' => $type->getValidTokenTime()->format(Api::DATE_FORMAT),
            'hasRoles' => null,
        ], json_decode((string) json_encode($type), true));
    }

    public function testSetGetId()
    {
        $this->assertNull((new EsiToken())->getId());
        $this->assertSame(5, (new EsiToken())->setId(5)->getId());
    }

    public function testSetGetCharacter()
    {
        $token = new EsiToken();
        $character = new Character();
        $token->setCharacter($character);
        $this->assertSame($character, $token->getCharacter());
    }

    public function testSetGetEveLogin()
    {
        $token = new EsiToken();
        $login = new EveLogin();
        $token->setEveLogin($login);
        $this->assertSame($login, $token->getEveLogin());
    }

    public function testSetGetRefreshToken()
    {
        $token = new EsiToken();
        $token->setRefreshToken('dfg');
        $this->assertSame('dfg', $token->getRefreshToken());
    }

    public function testSetGetAccessToken()
    {
        $token = new EsiToken();
        $token->setAccessToken('123');
        $this->assertSame('123', $token->getAccessToken());
    }

    public function testSetGetExpires()
    {
        $token = new EsiToken();
        $token->setExpires(456);
        $this->assertSame(456, $token->getExpires());
    }

    public function testSetGetValidToken()
    {
        $token = new EsiToken();

        $this->assertNull($token->getValidToken());
        $this->assertTrue($token->setValidToken(true)->getValidToken());
        $this->assertFalse($token->setValidToken(false)->getValidToken());
        $this->assertNull($token->setValidToken()->getValidToken());
    }

    public function testSetValidTokenUpdatesTime()
    {
        $token = new EsiToken();

        $this->assertNull($token->getValidTokenTime());
        $this->assertNull($token->getValidToken());

        $token->setValidToken();
        $this->assertNull($token->getValidTokenTime());

        $token->setValidToken(false);
        $time1 = $token->getValidTokenTime();
        $this->assertNotNull($time1);

        $token->setValidToken(true);
        $time2 = $token->getValidTokenTime();
        $this->assertNotSame($time1, $time2);
        $this->assertNotNull($time2);

        $token->setValidToken();
        $time3 = $token->getValidTokenTime();
        $this->assertNotSame($time2, $time3);
        $this->assertNotNull($token->getValidTokenTime());
    }

    public function testSetGetValidTokenTime()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:35');

        $token = new EsiToken();
        $token->setValidTokenTime($dt1);
        $dt2 = $token->getValidTokenTime();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }
}
