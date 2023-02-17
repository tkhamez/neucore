<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Core\Data;
use Neucore\Plugin\Core\DataInterface;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Plugin\Data\CoreEsiToken;
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

        $corp = (new Corporation())->setId(1020)->setName('C1');
        self::$helper->getEm()->persist($corp);
        $char = self::$helper->addCharacterMain('Main', 102030, [], ['G1'])->setCorporation($corp);
        $corp->addCharacter($char);
        $player = $char->getPlayer();
        self::$helper->addCharacterToPlayer('Alt 1', 102031, $player);
        self::$helper->createOrUpdateEsiToken(
            $char, time(), 'at', true, 'test.login', ['scope1'], ['role1'], new \DateTime()
        );
        self::$playerId = $player->getId();

        self::$helper->getEm()->flush();
        self::$helper->getEm()->clear();
    }

    protected function setUp(): void
    {
        $repositoryFactory = new RepositoryFactory(self::$helper->getEm());
        $this->data = new Data($repositoryFactory);
    }

    public function testGetCharactersByCorporation()
    {
        $result = $this->data->getCharactersByCorporation(1020);

        $this->assertSame(1, count($result));
        $this->assertSame(self::$playerId, $result[0]->playerId);
        $this->assertSame(102030, $result[0]->id);
        $this->assertNull($result[0]->corporationName);
        $this->assertNull($result[0]->allianceName);
    }

    public function testGetCharacter()
    {
        $result = $this->data->getCharacter(102031);
        $this->assertInstanceOf(CoreCharacter::class, $result);
        $this->assertSame(102031, $result->id);

        $this->assertNull($this->data->getCharacter(908070));
    }

    public function testGetCharacterTokens()
    {
        $result = $this->data->getCharacterTokens(102030);
        $this->assertSame(2, count($result));

        $this->assertInstanceOf(CoreEsiToken::class, $result[0]);
        $this->assertSame(102030, $result[0]->character->id);
        $this->assertNull($result[0]->character->corporationName);
        $this->assertNull($result[0]->character->allianceName);
        $this->assertSame(EveLogin::NAME_DEFAULT, $result[0]->eveLoginName);
        $this->assertSame([], $result[0]->esiScopes);
        $this->assertSame([], $result[0]->eveRoles);
        $this->assertNull($result[0]->valid);
        $this->assertNull($result[0]->hasRoles);
        $this->assertNull($result[0]->lastChecked);

        $this->assertInstanceOf(CoreEsiToken::class, $result[1]);
        $this->assertSame(102030, $result[1]->character->id);
        $this->assertSame('test.login', $result[1]->eveLoginName);
        $this->assertSame(['scope1'], $result[1]->esiScopes);
        $this->assertSame(['role1'], $result[1]->eveRoles);
        $this->assertTrue($result[1]->valid);
        $this->assertFalse($result[1]->hasRoles);
        $this->assertInstanceOf(\DateTime::class, $result[1]->lastChecked);

        $this->assertNull($this->data->getCharacter(908070));
    }

    public function testGetPlayerId()
    {
        $this->assertSame(self::$playerId, $this->data->getPlayerId(102030));
        $this->assertSame(self::$playerId, $this->data->getPlayerId(102031));
        $this->assertNull($this->data->getPlayerId(908070));
    }

    public function testGetEveLoginNames()
    {
        $this->assertSame(
            [EveLogin::NAME_DEFAULT, 'test.login'],
            $this->data->getEveLoginNames()
        );
    }

    public function testGetLoginTokens()
    {
        $this->assertSame([], $this->data->getLoginTokens(EveLogin::NAME_DEFAULT));

        $result = $this->data->getLoginTokens('test.login');
        $this->assertSame(1, count($result));
        $this->assertInstanceOf(CoreEsiToken::class, $result[0]);
        $this->assertSame(102030, $result[0]->character->id);
        $this->assertSame('Main', $result[0]->character->name);
        $this->assertSame('test.login', $result[0]->eveLoginName);
    }

    public function testGetGroups()
    {
        $result = $this->data->getGroups();
        $this->assertSame(1, count($result));
        $this->assertGreaterThan(0, $result[0]->identifier);
        $this->assertSame('G1', $result[0]->name);
    }
}
