<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Entity\Corporation;
use PHPUnit\Framework\TestCase;

class CharacterTest extends TestCase
{
    public function testJsonSerialize()
    {
        $corp = (new Corporation())->setName('c');
        $token = (new EsiToken())->setEveLogin((new EveLogin())->setId(1));
        $char = new Character();
        $char->setId(123);
        $char->setName('test char');
        $char->setMain(false);
        $char->addCharacterNameChange((new CharacterNameChange())->setOldName('old name'));
        $char->setCorporation($corp);
        $char->addEsiToken($token);

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
            'created' => null,
            'lastUpdate' => null,
            'validToken' => null,
            'validTokenTime' => null,
            'corporation' => $corp->jsonSerialize(),
        ], json_decode((string) json_encode($char), true));

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
            'created' => null,
            'lastUpdate' => null,
            'validToken' => null,
            'validTokenTime' => null,
            'characterNameChanges' => [['oldName' => 'old name', 'changeDate' => null]],
        ], json_decode((string) json_encode($char->jsonSerialize(false, false, true)), true));

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
            'created' => null,
            'lastUpdate' => null,
            'validToken' => null,
            'validTokenTime' => null,
        ], $char->jsonSerialize(false, false));

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
        ], $char->jsonSerialize(true));

        $this->assertSame([
            'id' => 123,
            'name' => 'test char',
            'main' => false,
            'created' => null,
            'lastUpdate' => null,
            'validToken' => null,
            'validTokenTime' => null,
            'corporation' => $corp->jsonSerialize(),
            'esiTokens' => [$token->jsonSerialize()],
        ], json_decode((string) json_encode($char->jsonSerialize(false, true, false, true)), true));
    }

    public function testSetGetId()
    {
        $char = new Character();
        $char->setId(123);
        $this->assertSame(123, $char->getId());
    }

    public function testSetGetName()
    {
        $char = new Character();
        $char->setName('nam');
        $this->assertSame('nam', $char->getName());
    }

    public function testSetGetMain()
    {
        $char = new Character();
        $this->assertFalse($char->getMain());
        $char->setMain(true);
        $this->assertTrue($char->getMain());
    }

    public function testSetGetPlayer()
    {
        $char = new Character();
        $player = new Player();
        $char->setPlayer($player);
        $this->assertSame($player, $char->getPlayer());
    }

    public function testSetGetCorporation()
    {
        $char = new Character();
        $corp = new Corporation();
        $char->setCorporation($corp);
        $this->assertSame($corp, $char->getCorporation());
    }

    public function testSetGetCharacterOwnerHash()
    {
        $char = new Character();
        $char->setCharacterOwnerHash('abc');
        $this->assertSame('abc', $char->getCharacterOwnerHash());
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

    public function testGetEsiToken()
    {
        $char = new Character();
        $token1 = (new EsiToken())->setEveLogin((new EveLogin())->setName(EveLogin::NAME_DEFAULT));
        $token2 = (new EsiToken())->setEveLogin((new EveLogin())->setName('another-login'));
        $char->addEsiToken($token1);
        $char->addEsiToken($token2);

        $this->assertNull($char->getEsiToken('does-not-exist'));
        $this->assertSame(EveLogin::NAME_DEFAULT, $char->getEsiToken(EveLogin::NAME_DEFAULT)->getEveLogin()->getName());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetCreated()
    {
        $dt1 = new \DateTime('2019-07-06 14:56:52');

        $char = new Character();
        $this->assertNull($char->getCreated());

        $char->setCreated($dt1);
        $dt2 = $char->getCreated();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2019-07-06T14:56:52+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastLogin()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:35');

        $char = new Character();
        $char->setLastLogin($dt1);
        $dt2 = $char->getLastLogin();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:35+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:36');

        $char = new Character();
        $char->setLastUpdate($dt1);
        $dt2 = $char->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:36+00:00', $dt2->format(\DateTimeInterface::ATOM));
    }

    public function testAddGetRemoveCharacterNameChanges()
    {
        $char = new Character();
        $cnc1 = new CharacterNameChange();
        $cnc2 = new CharacterNameChange();

        $this->assertSame([], $char->getCharacterNameChanges());

        $char->addCharacterNameChange($cnc1);
        $char->addCharacterNameChange($cnc2);
        $this->assertSame([$cnc1, $cnc2], $char->getCharacterNameChanges());

        $char->removeCharacterNameChange($cnc2);
        $this->assertSame([$cnc1], $char->getCharacterNameChanges());
    }

    public function testToCoreCharacter()
    {
        $character = (new Character())
            ->setId(100)
            ->setName('char name')
            ->setPlayer((new Player())->setId(1))
            ->setCorporation((new Corporation())
                ->setId(10)
                ->setName('corp name')
                ->setTicker('-C-')
                ->setAlliance((new Alliance())
                    ->setId(1)
                    ->setName('alli name')
                    ->setTicker('-A-')
                )
            );

        $coreCharacter = $character->toCoreCharacter();

        $this->assertSame(100, $coreCharacter->id);
        $this->assertSame(1, $coreCharacter->playerId);
        $this->assertSame('char name', $coreCharacter->name);
        $this->assertSame(10, $coreCharacter->corporationId);
        $this->assertSame('corp name', $coreCharacter->corporationName);
        $this->assertSame('-C-', $coreCharacter->corporationTicker);
        $this->assertSame(1, $coreCharacter->allianceId);
        $this->assertSame('alli name', $coreCharacter->allianceName);
        $this->assertSame('-A-', $coreCharacter->allianceTicker);
    }
}
