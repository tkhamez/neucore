<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use PHPUnit\Framework\TestCase;

class EveLoginTest extends TestCase
{
    public function testJsonSerialize()
    {
        $login = new EveLogin();
        $login->setId('the-id');
        $login->setName('the name');
        $login->setDescription('desc');
        $login->setEsiScopes('scope1 scope2');
        $login->setEveRoles(['role1', 'role2']);
        $login->addEsiToken(new EsiToken());

        $this->assertSame([
            'id' => 'the-id',
            'name' => 'the name',
            'description' => 'desc',
            'esiScopes' => 'scope1 scope2',
            'eveRoles' => ['role1', 'role2'],
        ], json_decode((string) json_encode($login), true));
    }

    public function testSetGetId()
    {
        $login = new EveLogin();
        $this->assertSame('the-id', $login->setId('the-id')->getId());
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
        $this->assertSame('scope1 scope2', $login->setEsiScopes('scope1 scope2')->getEsiScopes());
    }

    public function testSetGetEveRoles()
    {
        $login = new EveLogin();
        $this->assertSame(['role1', 'role2'], $login->setEveRoles(['role1', 'role2'])->getEveRoles());
    }

    /** @noinspection DuplicatedCode */
    public function testAddGetRemoveEsiToken()
    {
        $char = new Character();
        $token1 = new EsiToken();
        $token2 = new EsiToken();

        $this->assertSame([], $char->getEsiTokens());

        $char->addEsiToken($token1);
        $char->addEsiToken($token2);
        $this->assertSame([$token1, $token2], $char->getEsiTokens());

        $char->removeEsiToken($token2);
        $this->assertSame([$token1], $char->getEsiTokens());
    }
}
