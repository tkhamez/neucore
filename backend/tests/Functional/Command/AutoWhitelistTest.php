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

class AutoWhitelistTest extends ConsoleTestCase
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
        $corp3->setAutoWhitelist(true); // was whitelisted before
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

        $watchlist = new Watchlist();
        $watchlist->setId(2)->setName('test')->addCorporation($corp1)->addWhitelistCorporation($corp3);
        $this->om->persist($watchlist);

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
            new Response(200, [], '[1012]') // getCorporationsCorporationIdMembers
        );

        $output = $this->runConsoleApp('auto-whitelist', ['id' => 2, '--sleep' => 0], [
            ClientInterface::class => $client,
        ]);

        $log = explode("\n", $output);
        $this->assertSame(8, count($log));
        $this->assertStringContainsString('Started "auto-whitelist"', $log[0]);
        $this->assertStringContainsString("  Collected data from player {$this->data['player1Id']}.", $log[1]);
        $this->assertStringContainsString("  Collected data from player {$this->data['player2Id']}.", $log[2]);
        $this->assertStringContainsString('  Checked corporation 2000102.', $log[3]);
        $this->assertStringContainsString('  Corporations to check: 1, checked: 1, whitelisted: 1', $log[4]);
        $this->assertStringContainsString('  List saved successfully.', $log[5]);
        $this->assertStringContainsString('Finished "auto-whitelist"', $log[6]);
        $this->assertStringContainsString('', $log[7]);

        $this->om->clear();

        $list = (new RepositoryFactory($this->om))->getWatchlistRepository()->find(2);
        $corp2 = (new RepositoryFactory($this->om))->getCorporationRepository()->find(2000102);
        $corp3 = (new RepositoryFactory($this->om))->getCorporationRepository()->find(2000103);

        $this->assertSame(1, count($list->getWhitelistCorporations()));
        $this->assertSame(2000102, $list->getWhitelistCorporations()[0]->getId());
        $this->assertTrue($corp2->getAutoWhitelist());
        $this->assertFalse($corp3->getAutoWhitelist());
    }
}
