<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Core\Data;
use Neucore\Plugin\Core\DataInterface;
use Neucore\Plugin\Data\CoreCharacter;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class DataTest extends TestCase
{
    private static Helper $helper;

    private static int $playerId;

    private DataInterface $data;

    public static function setUpBeforeClass(): void
    {
        self::$helper = new Helper();
        self::$helper->emptyDb();

        $player = self::$helper->addCharacterMain('Main', 102030)->getPlayer();
        self::$helper->addCharacterToPlayer('Alt 1', 102031, $player);
        self::$playerId = $player->getId();

        self::$helper->getEm()->flush();
        self::$helper->getEm()->clear();
    }

    protected function setUp(): void
    {
        $repositoryFactory = new RepositoryFactory(self::$helper->getEm());
        $this->data = new Data(
            $repositoryFactory,
        );
    }

    public function testGetCharacter()
    {
        $this->assertInstanceOf(CoreCharacter::class, $this->data->getCharacter(102031));
        $this->assertNull($this->data->getCharacter(908070));
    }

    public function testGetPlayerId()
    {
        $this->assertSame(self::$playerId, $this->data->getPlayerId(102030));
        $this->assertSame(self::$playerId, $this->data->getPlayerId(102031));
        $this->assertNull($this->data->getPlayerId(908070));
    }
}
