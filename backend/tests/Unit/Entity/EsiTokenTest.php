<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use PHPUnit\Framework\TestCase;

class EsiTokenTest extends TestCase
{
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

}
