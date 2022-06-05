<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use PHPUnit\Framework\TestCase;

class EveLoginTest extends TestCase
{
    public function testIsValidObject()
    {
        $this->assertFalse(EveLogin::isValidObject(new \stdClass()));
        $this->assertFalse(EveLogin::isValidObject((object)['id' => 'custom1']));
        $this->assertFalse(EveLogin::isValidObject((object)[
            'id' => 0,
            'name' => 'custom1',
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => '',
        ]));
        $this->assertFalse(EveLogin::isValidObject((object)[
            'id' => null,
            'name' => '',
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => [],
        ]));

        $this->assertTrue(EveLogin::isValidObject((object)[
            'id' => 1,
            'name' => 'custom1',
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => [],
        ]));
        $this->assertTrue(EveLogin::isValidObject((object)[
            'id' => 0,
            'name' => '',
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => [''],
        ]));
        $this->assertTrue(EveLogin::isValidObject((object)[
            'id' => 1,
            'name' => 'custom1',
            'description' => 'd',
            'esiScopes' => 's',
            'eveRoles' => ['r'],
        ]));
    }

    public function testJsonSerialize()
    {
        $login = new EveLogin();
        $login->setId(10);
        $login->setName('the name');
        $login->setDescription('desc');
        $login->setEsiScopes('scope1 scope2');
        $login->setEveRoles(['role1', 'role2']);
        $login->addEsiToken(new EsiToken());

        $this->assertSame([
            'id' => 10,
            'name' => 'the name',
            'description' => 'desc',
            'esiScopes' => 'scope1 scope2',
            'eveRoles' => ['role1', 'role2'],
        ], json_decode((string) json_encode($login), true));
    }

    public function testSetGetId()
    {
        $login = new EveLogin();
        $this->assertSame(10, $login->setId(10)->getId());
    }

    public function testSetGetName()
    {
        $login = new EveLogin();
        $this->assertSame('the name', $login->setName('the name')->getName());
    }

    public function testSetGetDescription()
    {
        $login = new EveLogin();
        $this->assertSame('desc', $login->setDescription('desc')->getDescription());
    }

    public function testSetGetEsiScopes()
    {
        $login = new EveLogin();
        $this->assertSame('', $login->setEsiScopes('')->getEsiScopes());
        $this->assertSame('scope1', $login->setEsiScopes('  scope1  ')->getEsiScopes());
        $this->assertSame('scope1 scope2', $login->setEsiScopes("\tscope1\t\t scope2\t")->getEsiScopes());
        $this->assertSame('scope1 scope2', $login->setEsiScopes("\n scope1\t\n \nscope2 \t\n")->getEsiScopes());
    }

    public function testSetGetEveRoles()
    {
        $login = new EveLogin();
        $this->assertSame(['role1', 'role2'], $login->setEveRoles(['role1', 'role2'])->getEveRoles());
    }

    public function testAddGetRemoveEsiToken()
    {
        $login = new EveLogin();
        /** @noinspection DuplicatedCode */
        $token1 = new EsiToken();
        $token2 = new EsiToken();

        $this->assertSame([], $login->getEsiTokens());

        $login->addEsiToken($token1);
        $login->addEsiToken($token2);
        $this->assertSame([$token1, $token2], $login->getEsiTokens());

        $login->removeEsiToken($token2);
        $this->assertSame([$token1], $login->getEsiTokens());
    }
}
