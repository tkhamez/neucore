<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Neucore\Api;
use Neucore\Entity\Corporation;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class AutoAllowlistTest extends ConsoleTestCase
{
    /**
     * @var ObjectManager
     */
    private $om;

    private $data = [];

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->om = $helper->getObjectManager();

        $corp1 = (new Corporation())->setId(2000101)->setName('corp1'); // watched
        $corp2 = (new Corporation())->setId(2000102)->setName('corp2'); // PAC
        $corp3 = (new Corporation())->setId(2000103)->setName('corp3'); // other corp
        $corp3->setAutoAllowlist(true); // was on allowlist before
        $this->om->persist($corp1);
        $this->om->persist($corp2);
        $this->om->persist($corp3);

        $char1a = $helper->addCharacterMain('char1a', 1011)->setCorporation($corp1);
        $helper->addCharacterToPlayer('char1b', 1012, $char1a->getPlayer())->setCorporation($corp2)
            ->setAccessToken($helper::generateToken([Api::SCOPE_MEMBERSHIP])[0])
            ->setExpires(time() + 1200)
            ->setValidToken(true);
        $helper->addCharacterToPlayer('char1c', 1013, $char1a->getPlayer())->setCorporation($corp3);

        $char2a = $helper->addCharacterMain('char2a', 1021)->setCorporation($corp1);
        $helper->addCharacterToPlayer('char2c', 1023, $char2a->getPlayer())->setCorporation($corp3);

        $watchlist1 = new Watchlist();
        $watchlist1->setId(1)->setName('test1')->addCorporation($corp2);
        $this->om->persist($watchlist1);

        $watchlist2 = new Watchlist();
        $watchlist2->setId(2)->setName('test2')->addCorporation($corp1)->addAllowlistCorporation($corp3);
        $this->om->persist($watchlist2);

        $this->om->flush();

        $this->data['player1Id'] = $char1a->getPlayer()->getId();
        $this->data['player2Id'] = $char2a->getPlayer()->getId();
    }

    /**
     * @throws \Exception
     */
    public function testExecute()
    {
        $client = new Client();
        $client->setResponse(
            new Response(200, [], '[1012]') // getCorporationsCorporationIdMembers watchlist 3
        );

        $output = $this->runConsoleApp('auto-allowlist', ['--sleep' => 0], [
            ClientInterface::class => $client,
        ]);

        $log = explode("\n", $output);
        $this->assertSame(11, count($log));
        $this->assertStringContainsString('Started "auto-allowlist"', $log[0]);
        $this->assertStringContainsString('  Processing watchlist 1', $log[1]);
        $this->assertStringContainsString("    Collected data from player {$this->data['player1Id']}.", $log[2]);
        $this->assertStringContainsString('    Corporations to check: 1, checked: 0, allowlist: 0', $log[3]);
        $this->assertStringContainsString('  Processing watchlist 2', $log[4]);
        $this->assertStringContainsString("    Collected data from player {$this->data['player1Id']}.", $log[5]);
        $this->assertStringContainsString("    Collected data from player {$this->data['player2Id']}.", $log[6]);
        $this->assertStringContainsString('    Checked corporation 2000102.', $log[7]);
        $this->assertStringContainsString('    Corporations to check: 1, checked: 1, allowlist: 1', $log[8]);
        $this->assertStringContainsString('Finished "auto-allowlist"', $log[9]);
        $this->assertStringContainsString('', $log[10]);

        $this->om->clear();

        $list = (new RepositoryFactory($this->om))->getWatchlistRepository()->find(2);
        $corp2 = (new RepositoryFactory($this->om))->getCorporationRepository()->find(2000102);
        $corp3 = (new RepositoryFactory($this->om))->getCorporationRepository()->find(2000103);

        $this->assertSame(1, count($list->getAllowlistCorporations()));
        $this->assertSame(2000102, $list->getAllowlistCorporations()[0]->getId());
        $this->assertTrue($corp2->getAutoAllowlist());
        $this->assertFalse($corp3->getAutoAllowlist());
    }
}
