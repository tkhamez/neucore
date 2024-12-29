<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\ClientInterface;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class SendInvalidTokenMailTest extends ConsoleTestCase
{
    private Client $client;

    private ObjectManager $om;

    private int $playerId;

    private RepositoryFactory $repoFactory;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->om = $helper->getObjectManager();
        $this->client = new Client();
        $this->repoFactory = new RepositoryFactory($this->om);
    }

    public function testExecuteNotActive()
    {
        $output = $this->runConsoleApp('send-invalid-token-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-invalid-token-mail"', $actual[0]);
        $this->assertStringEndsWith('  Mail is deactivated.', $actual[1]);
        $this->assertStringEndsWith('Finished "send-invalid-token-mail"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    public function testExecuteMisconfiguration()
    {
        $deactivateAccounts = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $active = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $alliances = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('1010');
        $corps = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('');
        $alliance = (new Alliance())->setId(1010)->setName('alli')->setTicker('A');
        $corp = (new Corporation())->setId(2020)->setName('corp')->setTicker('C')->setAlliance($alliance);
        $player = (new Player())->setName('play');
        $char = (new Character())->setId(30)->setName('c3')->setPlayer($player)->setCorporation($corp);
        $this->om->persist($deactivateAccounts);
        $this->om->persist($active);
        $this->om->persist($alliances);
        $this->om->persist($corps);
        $this->om->persist($alliance);
        $this->om->persist($corp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();

        $output = $this->runConsoleApp('send-invalid-token-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-invalid-token-mail"', $actual[0]);
        $this->assertStringEndsWith('  Missing character that can send mails or missing token data.', $actual[1]);
        $this->assertStringEndsWith('Finished "send-invalid-token-mail"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    public function testExecuteRequestException()
    {
        $this->setupData();

        $client = new Client();
        $client->setMiddleware(function () {
            throw new RuntimeException("'error_label': 'ContactCostNotApproved'", 520);
        });
        $client->setResponse(new Response());
        $log = new Logger();

        $output = $this->runConsoleApp('send-invalid-token-mail', ['--sleep' => 0], [
            ClientInterface::class => $client,
            LoggerInterface::class => $log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-invalid-token-mail"', $actual[0]);
        $this->assertStringEndsWith(
            '  Invalid token mail could not be sent to 30 because of CSPA charge or blocked sender',
            $actual[1],
        );
        $this->assertStringEndsWith('Finished "send-invalid-token-mail"', $actual[2]);
        $this->assertSame('', $actual[3]);

        $this->assertSame(0, count($log->getHandler()->getRecords()));

        $this->om->clear();
        $player = $this->repoFactory->getPlayerRepository()->find($this->playerId);
        $this->assertTrue($player->getDeactivationMailSent());
    }

    public function testExecute()
    {
        $this->setupData();

        $this->client->setResponse(new Response(200, [], '373515628'));

        $output = $this->runConsoleApp('send-invalid-token-mail', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-invalid-token-mail"', $actual[0]);
        $this->assertStringEndsWith('  Invalid token mail sent to 30', $actual[1]);
        $this->assertStringEndsWith('Finished "send-invalid-token-mail"', $actual[2]);
        $this->assertSame('', $actual[3]);

        $this->om->clear();
        $player = $this->repoFactory->getPlayerRepository()->find($this->playerId);
        $this->assertTrue($player->getDeactivationMailSent());
    }

    private function setupData(): void
    {
        $active = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $alliances = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('1010');
        $corps = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('');
        $token = (new SystemVariable(SystemVariable::MAIL_TOKEN))
            ->setValue('{"id": 90, "access": "abc", "refresh": "", "expires": ""}');
        $subj = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('subj');
        $body = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY))->setValue('text');
        $alliance = (new Alliance())->setId(1010)->setName('alli')->setTicker('A');
        $corp = (new Corporation())->setId(2020)->setName('corp')->setTicker('C')->setAlliance($alliance);
        $p1 = (new Player())->setName('p1'); // no character
        $p2 = (new Player())->setName('p2');
        $p3 = (new Player())->setName('p3');
        $p4 = (new Player())->setName('p4')->setDeactivationMailSent(true);
        $p5 = (new Player())->setName('p5')->setStatus(Player::STATUS_MANAGED);
        $c2 = (new Character())->setId(20)->setName('c2')->setPlayer($p2); // not in correct alliance
        $c3 = (new Character())->setId(30)->setName('c3')->setPlayer($p3)->setCorporation($corp); // sends mail
        $c4 = (new Character())->setId(40)->setName('c4')->setPlayer($p4)->setCorporation($corp); // already sent
        $c5 = (new Character())->setId(50)->setName('c5')->setPlayer($p5)->setCorporation($corp); // managed account
        $this->om->persist($active);
        $this->om->persist($alliances);
        $this->om->persist($corps);
        $this->om->persist($token);
        $this->om->persist($subj);
        $this->om->persist($body);
        $this->om->persist($alliance);
        $this->om->persist($corp);
        $this->om->persist($p1);
        $this->om->persist($p2);
        $this->om->persist($p3);
        $this->om->persist($p4);
        $this->om->persist($p5);
        $this->om->persist($c2);
        $this->om->persist($c3);
        $this->om->persist($c4);
        $this->om->persist($c5);
        $this->om->flush();

        $this->playerId = $p3->getId();
    }
}
