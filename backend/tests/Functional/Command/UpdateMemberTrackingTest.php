<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateMemberTrackingTest extends ConsoleTestCase
{
    private ObjectManager $om;

    private Client $client;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();
        $this->client = new Client();
    }

    public function testExecuteErrorCorp()
    {
        $this->addData(true, false, false);

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Corporation not found for Director', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteErrorToken()
    {
        $this->addData(false, true, false);
        $this->client->setResponse(new Response(400, [], '{"error": "invalid_grant"}'));

        $output = $this->runConsoleApp(
            'update-member-tracking',
            ['--sleep' => 0],
            [ClientInterface::class => $this->client]
        );

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Error refreshing token for Director', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteErrorData()
    {
        $this->addData(false, false, false);

        $this->client->setResponse(new Response(500));

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => new Logger('test') // ignore the log entry
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Start updating Corporation', $actual[1]);
        $this->assertStringEndsWith('  Error getting member tracking data from ESI for Director', $actual[2]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);
    }

    public function testExecuteSuccess()
    {
        $this->addData();

        $this->client->setResponse(
            new Response(200, [], '[{"character_id": 100}]'), // corporations/1/membertracking/
            new Response(200, [], '[{"category": "character", "id": "100", "name": "Paul"}]'), // universe/names/
            new Response(200, [], '[]'), // structure
            new Response(200, [], '[]'), // postUniverseNames for char names
            new Response(200, [], '[]') // corporations/2/membertracking/
        );

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0], [
            ClientInterface::class => $this->client
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(9, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Start updating Corporation', $actual[1]);
        $this->assertStringEndsWith('  Updated ship/system/station names', $actual[2]);
        $this->assertStringEndsWith('  Updated structure names', $actual[3]);
        $this->assertStringEndsWith('  Updated tracking data for 1 members of corporation 100200', $actual[4]);
        $this->assertStringEndsWith('  Start updating Corp 2', $actual[5]);
        $this->assertStringEndsWith('  Updated tracking data for 0 members of corporation 100202', $actual[6]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[7]);
        $this->assertStringEndsWith('', $actual[8]);

        $this->om->clear();
        $corps = (new RepositoryFactory($this->om))->getCorporationRepository()->findBy([]);
        $this->assertSame(2, count($corps));
        $this->assertNotNull($corps[0]->getTrackingLastUpdate());
        $this->assertNotNull($corps[1]->getTrackingLastUpdate());
    }

    private function addData($noCorporation = false, $expiredToken = false, $addSecond = true)
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_TRACKING);
        $corporation1 = (new Corporation())->setId(100200)->setName('Corporation');
        $corporation2 = (new Corporation())->setId(100202)->setName('Corp 2');
        $player = (new Player())->setName('Director');
        $character1 = (new Character())->setId(100200300)->setName('Director')->setPlayer($player);
        $character2 = (new Character())->setId(100200302)->setName('Dir 2')->setPlayer($player);
        if (!$noCorporation) {
            $character1->setCorporation($corporation1);
            $character2->setCorporation($corporation2);
        }
        $esiToken1 = (new EsiToken())->setEveLogin($eveLogin)->setCharacter($character1)
            ->setValidToken(true)->setHasRoles(true)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time() + ($expiredToken ? -60 : 60));
        $this->om->persist($esiToken1);
        if ($addSecond) {
            $esiToken2 = (new EsiToken())->setEveLogin($eveLogin)->setCharacter($character2)
                ->setValidToken(true)->setHasRoles(true)
                ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time() + ($expiredToken ? -60 : 60));
            $this->om->persist($esiToken2);
        }
        $this->om->persist($eveLogin);
        $this->om->persist($corporation1);
        $this->om->persist($corporation2);
        $this->om->persist($player);
        $this->om->persist($character1);
        $this->om->persist($character2);
        $this->om->flush();
        $this->om->clear();
    }
}
