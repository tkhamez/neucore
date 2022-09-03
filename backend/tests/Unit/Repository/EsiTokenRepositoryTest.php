<?php
/** @noinspection DuplicatedCode */

namespace Tests\Unit\Repository;

use Doctrine\Persistence\ObjectManager;
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
    private ObjectManager $om;

    private EsiTokenRepository $repository;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();
        $this->repository = (new RepositoryFactory($this->om))->getEsiTokenRepository();
    }

    public function testFindByCorporation()
    {
        $login1 = (new EveLogin())->setName(EveLogin::NAME_TRACKING);
        $login2 = (new EveLogin())->setName('Other');
        $corp = (new Corporation())->setId(100)->setName('Corp 100');
        $player = (new Player())->setName('Player');
        $char = (new Character())->setId(100200)->setName('Char 100200')->setPlayer($player)->setCorporation($corp);
        $token1 = (new EsiToken())->setEveLogin($login1)->setCharacter($char)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $token2 = (new EsiToken())->setEveLogin($login2)->setCharacter($char)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $this->om->persist($login1);
        $this->om->persist($login2);
        $this->om->persist($corp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->persist($token1);
        $this->om->persist($token2);
        $this->om->flush();
        $this->om->clear();

        $result = $this->repository->findByLoginAndCorporation(EveLogin::NAME_TRACKING, 100);

        $this->assertSame(1, count($result));
        $this->assertSame(100, $result[0]->getCharacter()->getCorporation()->getId());
    }
}
