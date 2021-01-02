<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Neucore\Plugin\ServiceAccountData;
use PHPUnit\Framework\TestCase;

class AccountDataTest extends TestCase
{
    public function testConstructJsonSerialize()
    {
        $data = new ServiceAccountData(1, 'u', 'p', 'e');
        $this->assertSame([
            'characterId' => 1,
            'username' => 'u',
            'password' => 'p',
            'email' => 'e',
            'status' => null,
        ], $data->jsonSerialize());
    }

    public function testGetCharacterId()
    {
        $this->assertSame(1, (new ServiceAccountData(1))->getCharacterId());
    }

    public function testSetGetUsername()
    {
        $data = new ServiceAccountData(1);
        $this->assertSame('u', $data->setUsername('u')->getUsername());
    }

    public function testSetGetPassword()
    {
        $data = new ServiceAccountData(1);
        $this->assertSame('p', $data->setPassword('p')->getPassword());
    }

    public function testSetGetEmail()
    {
        $data = new ServiceAccountData(1);
        $this->assertSame('e', $data->setEmail('e')->getEmail());
    }

    public function testSetGetStatus()
    {
        $data = new ServiceAccountData(1);
        $this->assertSame(null, $data->setStatus('invalid')->getStatus());
        $this->assertSame(
            ServiceAccountData::STATUS_ACTIVE,
            $data->setStatus(ServiceAccountData::STATUS_ACTIVE)->getStatus()
        );
    }
}
