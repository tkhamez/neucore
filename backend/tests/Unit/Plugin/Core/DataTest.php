<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
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

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        self::$helper = new Helper();
        self::$helper->emptyDb();

        $alliance = (new Alliance())->setId(10)->setName('A1');
        $corp1 = (new Corporation())->setId(1020)->setName('C1');
        $corp2 = (new Corporation())->setId(2040)->setName('C2');
        self::$helper->getEm()->persist($alliance);
        self::$helper->getEm()->persist($corp1);
        self::$helper->getEm()->persist($corp2);
        $groups = self::$helper->addGroups(['G1', 'G2']);
        $corp1->addGroup($groups[0]);
        $alliance->addGroup($groups[1]);
        $char = self::$helper->addCharacterMain('Play', 102030, [], ['G1'], true, null, 123, true)
            ->setCorporation($corp1)->setName('Main');
        $char->getEsiToken(EveLogin::NAME_DEFAULT)->setLastChecked(new \DateTime());
        $corp1->addCharacter($char);
        $player = $char->getPlayer();
        $char2 = self::$helper->addCharacterToPlayer('Alt 1', 102031, $player)->setCorporation($corp1);
        self::$helper->createOrUpdateEsiToken(
            $char,
            time(),
            'at',
            true,
            'test.login',
            ['scope1'],
            ['role1'],
            new \DateTime(),
        );
        self::$playerId = $player->getId();

        // member tracking
        $location = (new EsiLocation())->setId(789)->setName('loc')->setCategory('station');
        $ship = (new EsiType())->setId(987)->setName('ship');
        $member1 = (new CorporationMember())
            ->setId($char->getId())
            ->setName($char->getName())
            ->setCorporation($corp1)
            ->setLogonDate(new \DateTime("@" . (time() - 1)))
            ->setLogoffDate(new \DateTime())
            ->setLocation($location)
            ->setShipType($ship)
            ->setStartDate(new \DateTime());
        $member2 = (new CorporationMember())
            ->setId($char2->getId())
            ->setName($char2->getName())
            ->setCorporation($corp1)
            ->setLogonDate(new \DateTime("@" . (time() - 2)))
            ->setLogoffDate(new \DateTime())
            ->setLocation($location)
            ->setShipType($ship)
            ->setStartDate(new \DateTime());
        self::$helper->getEm()->persist($member1);
        self::$helper->getEm()->persist($member2);
        self::$helper->getEm()->persist($location);
        self::$helper->getEm()->persist($ship);


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
        $this->assertSame([102031, 102030], $this->data->getCharacterIdsByCorporation(1020));
    }

    public function testGetCharactersByCorporation()
    {
        $this->assertNull($this->data->getCharactersByCorporation(9999));
        $this->assertSame([], $this->data->getCharactersByCorporation(2040));

        $result = $this->data->getCharactersByCorporation(1020);
        $this->assertSame(2, count($result));
        $this->assertSame(self::$playerId, $result[0]->playerId);
        $this->assertSame(102031, $result[0]->id);
        $this->assertNull($result[0]->corporationName);
        $this->assertNull($result[0]->allianceName);
        $this->assertSame(102030, $result[1]->id);
    }

    public function testGetMemberTracking()
    {
        $this->assertNull($this->data->getMemberTracking(9999));

        $result = $this->data->getMemberTracking(1020);
        $this->assertSame(2, count($result));

        $this->assertSame(102030, $result[0]->character->id);
        $this->assertSame(self::$playerId, $result[0]->character->playerId);
        $this->assertTrue($result[0]->character->main);
        $this->assertSame('Main', $result[0]->character->name);
        $this->assertSame('Play', $result[0]->character->playerName);
        $this->assertTrue($result[0]->defaultToken->valid);
        $this->assertInstanceOf(\DateTime::class, $result[0]->defaultToken->validStatusChanged);
        $this->assertInstanceOf(\DateTime::class, $result[0]->defaultToken->lastChecked);
        $this->assertInstanceOf(\DateTime::class, $result[0]->logonDate);
        $this->assertInstanceOf(\DateTime::class, $result[0]->logoffDate);
        $this->assertSame(789, $result[0]->locationId);
        $this->assertSame('loc', $result[0]->locationName);
        $this->assertSame('station', $result[0]->locationCategory);
        $this->assertSame(987, $result[0]->shipTypeId);
        $this->assertSame('ship', $result[0]->shipTypeName);
        $this->assertInstanceOf(\DateTime::class, $result[0]->joinDate);

        $this->assertSame(2, count($result));
        $this->assertSame(102031, $result[1]->character->id);
        $this->assertSame(self::$playerId, $result[1]->character->playerId);
        $this->assertFalse($result[1]->character->main);
        $this->assertSame('Alt 1', $result[1]->character->name);
        $this->assertSame('Play', $result[1]->character->playerName);
        $this->assertNull($result[1]->defaultToken->valid); // char does not have a token, but object exists here
        $this->assertNull($result[1]->defaultToken->validStatusChanged);
        $this->assertNull($result[1]->defaultToken->lastChecked);
        $this->assertInstanceOf(\DateTime::class, $result[1]->logonDate);
        $this->assertInstanceOf(\DateTime::class, $result[1]->logoffDate);
        $this->assertSame(789, $result[1]->locationId);
        $this->assertSame('loc', $result[1]->locationName);
        $this->assertSame('station', $result[1]->locationCategory);
        $this->assertSame(987, $result[1]->shipTypeId);
        $this->assertSame('ship', $result[1]->shipTypeName);
        $this->assertInstanceOf(\DateTime::class, $result[1]->joinDate);
    }

    public function testGetCharacter()
    {
        $this->assertNull($this->data->getCharacter(888999));

        $result = $this->data->getCharacter(102031);
        $this->assertInstanceOf(CoreCharacter::class, $result);
        $this->assertSame(102031, $result->id);
    }

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
        $this->assertTrue($result[0]->valid);
        $this->assertNull($result[0]->hasRoles);
        $this->assertInstanceOf(\DateTime::class, $result[0]->lastChecked);

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
            $this->data->getEveLoginNames(),
        );
    }

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
        $this->assertSame(2, count($result));
        $this->assertGreaterThan(0, $result[0]->identifier);
        $this->assertGreaterThan(0, $result[1]->identifier);
        $this->assertSame('G1', $result[0]->name);
        $this->assertSame('G2', $result[1]->name);
    }

    public function testGetCorporationGroups()
    {
        $this->assertNull($this->data->getCorporationGroups(99887778));

        $result = $this->data->getCorporationGroups(1020);
        $this->assertSame(1, count($result));
        $this->assertGreaterThan(0, $result[0]->identifier);
        $this->assertSame('G1', $result[0]->name);
    }

    public function testGetAllianceGroups()
    {
        $this->assertNull($this->data->getAllianceGroups(99887778));

        $result = $this->data->getAllianceGroups(10);
        $this->assertSame(1, count($result));
        $this->assertGreaterThan(0, $result[0]->identifier);
        $this->assertSame('G2', $result[0]->name);
    }
}
