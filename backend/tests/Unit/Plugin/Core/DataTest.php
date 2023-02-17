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

        $corp1 = (new Corporation())->setId(1020)->setName('C1');
        $corp2 = (new Corporation())->setId(2040)->setName('C2');
        self::$helper->getEm()->persist($corp1);
        self::$helper->getEm()->persist($corp2);
        $char = self::$helper->addCharacterMain('Main', 102030, [], ['G1'])->setCorporation($corp1);
        $corp1->addCharacter($char);
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

    public function testGetCharacterIdsByCorporation()
    {
        $this->assertNull($this->data->getCharacterIdsByCorporation(9999));
        $this->assertSame([], $this->data->getCharacterIdsByCorporation(2040));
        $this->assertSame([102030], $this->data->getCharacterIdsByCorporation(1020));
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetCharactersByCorporation()
    {
        $this->assertNull($this->data->getCharactersByCorporation(9999));
        $this->assertSame([], $this->data->getCharactersByCorporation(2040));

        $result = $this->data->getCharactersByCorporation(1020);
        $this->assertSame(1, count($result));
        $this->assertSame(self::$playerId, $result[0]->playerId);
        $this->assertSame(102030, $result[0]->id);
        $this->assertNull($result[0]->corporationName);
        $this->assertNull($result[0]->allianceName);
    }

    public function testGetCharacter()
    {
        $this->assertNull($this->data->getCharacter(888999));

        $result = $this->data->getCharacter(102031);
        $this->assertInstanceOf(CoreCharacter::class, $result);
        $this->assertSame(102031, $result->id);
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetCharacterTokens()
    {
        $this->assertNull($this->data->getCharacterTokens(888999));

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
    }

    public function testGetPlayerId()
    {
        $this->assertNull($this->data->getPlayerId(888999));

        $this->assertSame(self::$playerId, $this->data->getPlayerId(102030));
        $this->assertSame(self::$playerId, $this->data->getPlayerId(102031));
    }

    public function testGetEveLoginNames()
    {
        $this->assertSame(
            [EveLogin::NAME_DEFAULT, 'test.login'],
            $this->data->getEveLoginNames()
        );
    }

    /**
     * @phan-suppress PhanTypeArraySuspiciousNullable
     */
    public function testGetLoginTokens()
    {
        $this->assertNull($this->data->getLoginTokens(EveLogin::NAME_DEFAULT));
        $this->assertNull($this->data->getLoginTokens('invalid'));

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
