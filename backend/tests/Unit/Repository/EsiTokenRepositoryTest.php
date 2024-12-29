<?php

/** @noinspection DuplicatedCode */

namespace Tests\Unit\Repository;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\EsiTokenRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class EsiTokenRepositoryTest extends TestCase
{
    private EsiTokenRepository $repository;

    private int $login1;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        $this->repository = (new RepositoryFactory($om))->getEsiTokenRepository();

        $login1 = (new EveLogin())->setName(EveLogin::NAME_TRACKING);
        $login2 = (new EveLogin())->setName('Other');
        $alliance = (new Alliance())->setId(10)->setName('Alliance 10');
        $corp = (new Corporation())->setId(100)->setName('Corp 100')->setAlliance($alliance);
        $player = (new Player())->setName('Player');
        $char1 = (new Character())->setId(100200)->setName('Char 100200')->setPlayer($player)->setCorporation($corp);
        $char2 = (new Character())->setId(100201)->setName('Char 100201')->setPlayer($player);
        $char3 = (new Character())->setId(100202)->setName('Char 100202')->setPlayer($player);
        $char4 = (new Character())->setId(100203)->setName('Char 100203')->setPlayer($player);
        $char5 = (new Character())->setId(100204)->setName('Char 100204')->setPlayer($player);
        $token1a = (new EsiToken())->setEveLogin($login1)->setCharacter($char1)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time())
            ->setValidToken(true)->setHasRoles(true)->setLastChecked(new \DateTime('2022-09-10 15:57:52'));
        $token1b = (new EsiToken())->setEveLogin($login1)->setCharacter($char2)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time())
            ->setValidToken(true)->setHasRoles(true);
        $token1c = (new EsiToken())->setEveLogin($login1)->setCharacter($char3)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time())
            ->setValidToken(true)->setHasRoles(false);
        $token1d = (new EsiToken())->setEveLogin($login1)->setCharacter($char4)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time())
            ->setValidToken(false)->setHasRoles(true);
        $token1de = (new EsiToken())->setEveLogin($login1)->setCharacter($char5)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time())
            ->setValidToken(true);
        $token2 = (new EsiToken())->setEveLogin($login2)->setCharacter($char1)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $om->persist($login1);
        $om->persist($login2);
        $om->persist($alliance);
        $om->persist($corp);
        $om->persist($player);
        $om->persist($char1);
        $om->persist($char2);
        $om->persist($char3);
        $om->persist($char4);
        $om->persist($char5);
        $om->persist($token1a);
        $om->persist($token1b);
        $om->persist($token1c);
        $om->persist($token1d);
        $om->persist($token1de);
        $om->persist($token2);
        $om->flush();
        $om->clear();

        $this->login1 = $login1->getId();
    }

    public function testFindByCorporation()
    {
        $result = $this->repository->findByLoginAndCorporation(EveLogin::NAME_TRACKING, 100);

        $this->assertSame(1, count($result));
        $this->assertSame(100, $result[0]->getCharacter()->getCorporation()->getId());
    }

    public function testFindCharacterIdsByLoginId()
    {
        $result = $this->repository->findCharacterIdsByLoginId($this->login1);
        $this->assertSame([100200, 100201, 100202, 100203, 100204], $result);
    }

    public function testFindValidTokens()
    {
        $result = $this->repository->findValidTokens($this->login1);

        $this->assertSame([
            [
                'lastChecked' => '2022-09-10T15:57:52Z',
                'characterId' => 100200,
                'characterName' => 'Char 100200',
                'corporationId' => 100,
                'allianceId' => 10,
            ],
            [
                'lastChecked' => null,
                'characterId' => 100201,
                'characterName' => 'Char 100201',
                'corporationId' => null,
                'allianceId' => null,
            ],
            [
                'lastChecked' => null,
                'characterId' => 100204,
                'characterName' => 'Char 100204',
                'corporationId' => null,
                'allianceId' => null,
            ],
        ], $result);
    }
}
