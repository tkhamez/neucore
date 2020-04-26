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
    /**
     * @var Watchlist
     */
    private static $watchlistService;

    /**
     * @var Character
     */
    private static $char1;

    /**
     * @var Character
     */
    private static $char2;

    /**
     * @var Character
     */
    private static $char3;

    /**
     * @var Character
     */
    private static $char4;

    /**
     * @var Corporation
     */
    private static $corp1a;

    /**
     * @var Corporation
     */
    private static $corp1b;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $group = (new Group())->setName('g1');

        $watchlist = (new \Neucore\Entity\Watchlist())->setId(1)->setName('w1');

        $watchlist->addGroup($group);

        $alliance1 = (new Alliance())->setId(11)->setName('a1'); // watched
        $alliance2 = (new Alliance())->setId(12)->setName('a2'); // white list
        $alliance3 = (new Alliance())->setId(13)->setName('a3'); // black list

        self::$corp1a = (new Corporation())->setId(1011)->setName('c1a')
            ->setAlliance($alliance1); // watched via alliance
        self::$corp1b = (new Corporation())->setId(1012)->setName('c1b'); // watched
        $corp2a = (new Corporation())->setId(1021)->setName('c2a')->setAlliance($alliance2); // white list via alliance
        $corp2b = (new Corporation())->setId(1022)->setName('c2b'); // white listed
        $corp3a = (new Corporation())->setId(1031)->setName('c3a')->setAlliance($alliance3); // black list via alliance
        $corp3b = (new Corporation())->setId(1032)->setName('c3b'); // black listed
        $corp4 = (new Corporation())->setId(2000000 + 1040)->setName('c4'); // other corp, not NPC

        $watchlist->addAlliance($alliance1);
        $watchlist->addCorporation(self::$corp1b);
        $watchlist->addWhitelistAlliance($alliance2);
        $watchlist->addBlacklistAlliance($alliance3);
        $watchlist->addWhitelistCorporation($corp2b);
        $watchlist->addBlacklistCorporation($corp3b);

        $helper->getObjectManager()->persist($watchlist);
        $helper->getObjectManager()->persist($group);
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

        self::$char1 = $helper->addCharacterMain('c1a', 10011)->setCorporation(self::$corp1a); // watched via alliance
        self::$char2 = $helper->addCharacterMain('c2a', 10021)->setCorporation(self::$corp1b); // watched
        self::$char3 = $helper->addCharacterMain('c3a', 10031)->setCorporation(self::$corp1b); // watched
        self::$char4 = $helper->addCharacterMain('c4a', 10041)->setCorporation(self::$corp1a); // watched via alliance

        $helper->addCharacterToPlayer('c1b', 10012, self::$char1->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c2b', 10022, self::$char2->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c3b', 10032, self::$char3->getPlayer())->setCorporation($corp4); // other corp
        $helper->addCharacterToPlayer('c4b', 10042, self::$char4->getPlayer())->setCorporation($corp4); // other corp

        $helper->addCharacterToPlayer('c2c', 10023, self::$char2->getPlayer())
            ->setCorporation($corp3a); // black list via alliance
        $helper->addCharacterToPlayer('c3c', 10033, self::$char3->getPlayer())
            ->setCorporation($corp3b); // black list

        $helper->addCharacterToPlayer('c3d', 10034, self::$char3->getPlayer())
            ->setCorporation($corp2a); // white listed corp, but also on black listed corp
        $watchlist->addExemption(self::$char4->getPlayer()); // whitelist player

        $helper->getObjectManager()->flush();

        self::$watchlistService = new Watchlist(new RepositoryFactory($helper->getEm()));
    }

    public function testGetRedFlagList()
    {
        $actual = self::$watchlistService->getRedFlagList(1);
        $this->assertSame(1, count($actual));
        $this->assertSame([
            'id' => self::$char1->getPlayer()->getId(),
            'name' => 'c1a',
        ], $actual[0]->jsonSerialize(true));
    }

    public function testGetRedFlagListWithBlacklistAndWhitelist()
    {
        $actual = self::$watchlistService->getRedFlagList(1, true, true);
        $this->assertSame(4, count($actual));
        $this->assertSame('c1a', $actual[0]->getName());
        $this->assertSame('c2a', $actual[1]->getName());
        $this->assertSame('c3a', $actual[2]->getName());
        $this->assertSame('c4a', $actual[3]->getName());
    }

    public function testGetBlacklist()
    {
        $actual = self::$watchlistService->getBlacklist(1);
        $this->assertSame([[
            'id' => self::$char2->getPlayer()->getId(),
            'name' => 'c2a',
        ], [
            'id' => self::$char3->getPlayer()->getId(),
            'name' => 'c3a', // white and black list via different corps
        ]], $actual);
    }

    public function testGetList()
    {
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::GROUP)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::ALLIANCE)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::EXEMPTION)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::BLACKLIST_CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::BLACKLIST_ALLIANCE)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::WHITELIST_CORPORATION)));
        $this->assertSame(1, count(self::$watchlistService->getList(1, Watchlist::WHITELIST_ALLIANCE)));

        $this->assertInstanceOf(Group::class, self::$watchlistService->getList(1, Watchlist::GROUP)[0]);
        $this->assertInstanceOf(Alliance::class, self::$watchlistService->getList(1, Watchlist::ALLIANCE)[0]);
        $this->assertInstanceOf(Corporation::class, self::$watchlistService->getList(1, Watchlist::CORPORATION)[0]);
        $this->assertInstanceOf(Player::class, self::$watchlistService->getList(1, Watchlist::EXEMPTION)[0]);
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getList(1, Watchlist::BLACKLIST_CORPORATION)[0]
        );
        $this->assertInstanceOf(
            Alliance::class,
            self::$watchlistService->getList(1, Watchlist::BLACKLIST_ALLIANCE)[0]
        );
        $this->assertInstanceOf(
            Corporation::class,
            self::$watchlistService->getList(1, Watchlist::WHITELIST_CORPORATION)[0]
        );
        $this->assertInstanceOf(
            Alliance::class,
            self::$watchlistService->getList(1, Watchlist::WHITELIST_ALLIANCE)[0]
        );
    }

    public function testGetCorporationIds()
    {
        $actual = self::$watchlistService->getCorporationIds(1, 'alliance', 'corporation');

        $this->assertSame([self::$corp1a->getId(), self::$corp1b->getId()], $actual);
    }
}
