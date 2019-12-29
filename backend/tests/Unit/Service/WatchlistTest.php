<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
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

        $corp1a = (new Corporation())->setId(1011)->setName('c1a')->setAlliance($alliance1); // watched via alliance
        $corp1b = (new Corporation())->setId(1012)->setName('c1b'); // watched
        $corp2a = (new Corporation())->setId(1021)->setName('c2a')->setAlliance($alliance2); // white list via alliance
        $corp2b = (new Corporation())->setId(1022)->setName('c2b'); // white listed
        $corp3a = (new Corporation())->setId(1031)->setName('c3a')->setAlliance($alliance3); // black list via alliance
        $corp3b = (new Corporation())->setId(1032)->setName('c3b'); // black listed
        $corp4 = (new Corporation())->setId(2000000 + 1040)->setName('c4'); // other corp, not NPC

        $watchlist->addAlliance($alliance1);
        $watchlist->addCorporation($corp1b);
        $watchlist->addWhitelistAlliance($alliance2);
        $watchlist->addBlacklistAlliance($alliance3);
        $watchlist->addWhitelistCorporation($corp2b);
        $watchlist->addBlacklistCorporation($corp3b);

        $helper->getEm()->persist($watchlist);
        $helper->getEm()->persist($group);
        $helper->getEm()->persist($alliance1);
        $helper->getEm()->persist($alliance2);
        $helper->getEm()->persist($alliance3);
        $helper->getEm()->persist($corp1a);
        $helper->getEm()->persist($corp1b);
        $helper->getEm()->persist($corp2a);
        $helper->getEm()->persist($corp2b);
        $helper->getEm()->persist($corp3a);
        $helper->getEm()->persist($corp3b);
        $helper->getEm()->persist($corp4);
        $helper->getEm()->flush();

        self::$char1 = $helper->addCharacterMain('c1a', 10011)->setCorporation($corp1a); // watched via alliance
        self::$char2 = $helper->addCharacterMain('c2a', 10021)->setCorporation($corp1b); // watched
        self::$char3 = $helper->addCharacterMain('c3a', 10031)->setCorporation($corp1b); // watched
        self::$char4 = $helper->addCharacterMain('c4a', 10041)->setCorporation($corp1a); // watched via alliance

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

        $helper->getEm()->flush();

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
        $this->markTestIncomplete('TODO'); # TODO
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
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'group')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'alliance')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'corporation')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'exemption')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'blacklistCorporations')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'blacklistAlliance')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'whitelistCorporation')));
        $this->assertSame(1, count(self::$watchlistService->getList(1, 'whitelistAlliance')));
    }

    public function testGetCorporationIds()
    {
        $this->markTestIncomplete('TODO'); # TODO
    }
}
