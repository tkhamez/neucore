<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Neucore\Plugin\AccountData;
use PHPUnit\Framework\TestCase;

class AccountDataTest extends TestCase
{
    public function testConstructJsonSerialize()
    {
        $data = new AccountData(1, 'u', 'p', 'e');
        $this->assertSame([
            'characterId' => 1,
            'username' => 'u',
            'password' => 'p',
            'email' => 'e',
        ], $data->jsonSerialize());
    }

    public function testGetCharacterId()
    {
        $this->assertSame(1, (new AccountData(1))->getCharacterId());
    }

    public function testSetGetUsername()
    {
        $data = new AccountData(1);
        $this->assertSame('u', $data->setUsername('u')->getUsername());
    }

    public function testSetGetPassword()
    {
        $data = new AccountData(1);
        $this->assertSame('p', $data->setPassword('p')->getPassword());
    }

    public function testSetGetEmail()
    {
        $data = new AccountData(1);
        $this->assertSame('e', $data->setEmail('e')->getEmail());
    }
}
