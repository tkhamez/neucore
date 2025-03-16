<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Watchlist;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class WatchlistTest extends TestCase
{
    private static Watchlist $watchlistService;

    private static Character $char1;

    private static Character $char2;

    private static Character $char3;

    private static Character $char4;

    private static Corporation $corp1a;

    private static Corporation $corp1b;

    private static int $watchlistId;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $group = (new Group())->setName('g1');
        $managerGroup = (new Group())->setName('g2');

        $watchlist = (new \Neucore\Entity\Watchlist())->setName('w1');

        $watchlist->addGroup($group);
        $watchlist->addManagerGroup($managerGroup);

        $alliance1 = (new Alliance())->setId(11)->setName('a1'); // watched
        $alliance2 = (new Alliance())->setId(12)->setName('a2'); // allowlist
        $alliance3 = (new Alliance())->setId(13)->setName('a3'); // kicklist

        self::$corp1a = (new Corporation())->setId(1011)->setName('c1a')
            ->setAlliance($alliance1); // watched via alliance
        self::$corp1b = (new Corporation())->setId(1012)->setName('c1b'); // watched
        $corp2a = (new Corporation())->setId(1021)->setName('c2a')->setAlliance($alliance2); // allowlist via alliance
        $corp2b = (new Corporation())->setId(1022)->setName('c2b'); // on allowlist
        $corp3a = (new Corporation())->setId(1031)->setName('c3a')->setAlliance($alliance3); // kicklist via alliance
        $corp3b = (new Corporation())->setId(1032)->setName('c3b'); // on kicklist
        $corp4 = (new Corporation())->setId(2000000 + 1040)->setName('c4'); // other corp, not NPC

        $watchlist->addAlliance($alliance1);
        $watchlist->addCorporation(self::$corp1b);
        $watchlist->addAllowlistAlliance($alliance2);
        $watchlist->addKicklistAlliance($alliance3);
        $watchlist->addAllowlistCorporation($corp2b);
        $watchlist->addKicklistCorporation($corp3b);

        $helper->getObjectManager()->persist($watchlist);
        $helper->getObjectManager()->persist($group);
        $helper->getObjectManager()->persist($managerGroup);
        $helper->getObjectManager()->persist($alliance1);
        $helper->getObjectManager()->persist($alliance2);
        $helper->getObjectManager()->persist($alliance3);
        $helper->getObjectManager()->persist(self::$corp1a);
        $helper->getObjectManager()->persist(self::$corp1b);
        $helper->getObjectManager()->persist($corp2a);
        $helper->getObjectManager()->persist($corp2b);
        $helper->getObjectManager()->persist($corp3a);
        $helper->getObjectManager()->persist($corp3b);
        $helper->getObjectManager()->persist($corp4);
        $helper->getObjectManager()->flush();

        self::$watchlistId = $watchlist->getId();

        self::$char1 = $helper->addCharacterMain('c1a', 10011)->setCorporation(self::$corp1a); // watched via alliance
        self::$char2 = $helper->addCharacterMain('c2a', 10021)->setCorporation(self::$corp1b); // watched
        self::$char3 = $helper->addCharacterMain('c3a', 10031)->setCorporation(self::$corp1b); // watched
        self::$char4 = $helper->addCharacterMain('c4a', 10041)->setCorporation(self::$corp1a); // watched via alliance

        $helper->addCharacterToPlayer('c1b', 10012, self::$char1->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c2b', 10022, self::$char2->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c3b', 10032, self::$char3->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c4b', 10042, self::$char4->getPlayer())->setCorporation($corp4); // other corp

        $helper->addCharacterToPlayer('c2c', 10023, self::$char2->getPlayer())
            ->setCorporation($corp3a); // kicklist via alliance
        $helper->addCharacterToPlayer('c3c', 10033, self::$char3->getPlayer())
            ->setCorporation($corp3b); // kicklist

        $helper->addCharacterToPlayer('c3d', 10034, self::$char3->getPlayer())
            ->setCorporation($corp2a); // corp on allowlist, but also on corp from kicklist
        $watchlist->addExemption(self::$char4->getPlayer()); // add to allowlist

        $helper->getObjectManager()->flush();

        self::$watchlistService = new Watchlist(new RepositoryFactory($helper->getEm()));
    }

    public function testGetWarningList()
    {
        $actual = self::$watchlistService->getWarningList(self::$watchlistId);
        $this->assertSame(1, count($actual));
        $this->assertSame([
            'id' => self::$char1->getPlayer()->getId(),
            'name' => 'c1a',
        ], $actual[0]->jsonSerialize(true));
    }

    public function testGetWarningListWithKicklistAndAllowlist()
    {
        $actual = self::$watchlistService->getWarningList(self::$watchlistId, true, true);
        $this->assertSame(4, count($actual));
        $this->assertSame('c1a', $actual[0]->getName());
        $this->assertSame('c2a', $actual[1]->getName());
        $this->assertSame('c3a', $actual[2]->getName());
        $this->assertSame('c4a', $actual[3]->getName());
    }

    public function testGetKicklist()
    {
        $actual = self::$watchlistService->getKicklist(self::$watchlistId);
        $this->assertSame([[
            'id' => self::$char2->getPlayer()->getId(),
            'name' => 'c2a',
        ], [
            'id' => self::$char3->getPlayer()->getId(),
            'name' => 'c3a', // allowlist and kicklist via different corps
        ]], $actual);
    }

    public function testGetList()
    {
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::GROUP)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::MANAGER_GROUP)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLIANCE)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::EXEMPTION)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::KICKLIST_CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::KICKLIST_ALLIANCE)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLOWLIST_CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLOWLIST_ALLIANCE)));

        $this->assertInstanceOf(
            Group::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::GROUP)[0],
        );
        $this->assertInstanceOf(
            Group::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::MANAGER_GROUP)[0],
        );
        $this->assertInstanceOf(
            Alliance::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLIANCE)[0],
        );
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::CORPORATION)[0],
        );
        $this->assertInstanceOf(
            Player::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::EXEMPTION)[0],
        );
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::KICKLIST_CORPORATION)[0],
        );
        $this->assertInstanceOf(
            Alliance::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::KICKLIST_ALLIANCE)[0],
        );
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLOWLIST_CORPORATION)[0],
        );
        $this->assertInstanceOf(
            Alliance::class,
            self::$watchlistService->getList(self::$watchlistId, Watchlist::ALLOWLIST_ALLIANCE)[0],
        );
    }

    public function testGetExemptionList()
    {
        $this->assertInstanceOf(
            Player::class,
            self::$watchlistService->getExemptionList(self::$watchlistId)[0],
        );
    }

    public function testGetAllowlistCorporationList()
    {
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getAllowlistCorporationList(self::$watchlistId)[0],
        );
    }

    public function testGetCorporationIds()
    {
        $actual = self::$watchlistService->getCorporationIds(
            self::$watchlistId,
            Watchlist::ALLIANCE,
            Watchlist::CORPORATION
        );

        $this->assertSame([self::$corp1a->getId(), self::$corp1b->getId()], $actual);
    }
}
