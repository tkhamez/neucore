<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class SendMissingCharacterMailTest extends ConsoleTestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

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
        $output = $this->runConsoleApp('send-missing-character-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-missing-character-mail"', $actual[0]);
        $this->assertStringEndsWith('  Mail is deactivated.', $actual[1]);
        $this->assertStringEndsWith('Finished "send-missing-character-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteInvalidConfig()
    {
        $active = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE))->setValue('1');
        $this->om->persist($active);
        $this->om->flush();

        $output = $this->runConsoleApp('send-missing-character-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-missing-character-mail"', $actual[0]);
        $this->assertStringEndsWith('  Invalid config.', $actual[1]);
        $this->assertStringEndsWith('Finished "send-missing-character-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteMisconfiguration()
    {
        $this->setupData(true);

        $output = $this->runConsoleApp('send-missing-character-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-missing-character-mail"', $actual[0]);
        $this->assertStringEndsWith('  Missing subject or body text.', $actual[1]);
        $this->assertStringEndsWith('Finished "send-missing-character-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteRequestException()
    {
        $this->setupData();

        $client = new Client();
        $client->setMiddleware(function () {
            throw new \Exception("'error_label': 'ContactCostNotApproved'", 520);
        });
        $client->setResponse(new Response());
        $log = new Logger('Test');

        $output = $this->runConsoleApp('send-missing-character-mail', ['--sleep' => 0], [
            ClientInterface::class => $client,
            LoggerInterface::class => $log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-missing-character-mail"', $actual[0]);
        $this->assertStringEndsWith(
            '  Mail could not be sent to 104 because of CSPA charge or blocked sender',
            $actual[1]
        );
        $this->assertStringEndsWith('Finished "send-missing-character-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        $this->assertSame(0, count($log->getHandler()->getRecords()));

        $this->om->clear();
        $member4 = $this->repoFactory->getCorporationMemberRepository()->find(104);
        $this->assertLessThanOrEqual(new \DateTime(), $member4->getMissingCharacterMailSent());
    }

    public function testExecute()
    {
        $this->setupData();

        $this->client->setResponse(new Response(200, [], '373515628'));

        $output = $this->runConsoleApp('send-missing-character-mail', ['--sleep' => 0], [
            ClientInterface::class => $this->client
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "send-missing-character-mail"', $actual[0]);
        $this->assertStringEndsWith('  Mail sent to 104', $actual[1]);
        $this->assertStringEndsWith('Finished "send-missing-character-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);

        $this->om->clear();
        $member4 = $this->repoFactory->getCorporationMemberRepository()->find(104);
        $this->assertLessThanOrEqual(new \DateTime(), $member4->getMissingCharacterMailSent());
    }

    private function setupData($invalidConfig = false)
    {
        $token = (new SystemVariable(SystemVariable::MAIL_TOKEN))
            ->setValue('{"id": 90, "access": "abc", "refresh": "", "expires": ""}');
        $active = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE))->setValue('1');
        $days = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_RESEND))->setValue('20');
        $corps = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS))->setValue('2010,2030');
        if (! $invalidConfig) {
            $subj = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT))->setValue('subj');
        }
        $body = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY))->setValue('text');
        $corp1 = (new Corporation())->setId(2010)->setName('corp1')->setTicker('C1')
            ->setTrackingLastUpdate(new \DateTime()); // tacked and up to date
        $corp2 = (new Corporation())->setId(2020)->setName('corp2')->setTicker('C2'); // not tracked
        $corp3 = (new Corporation())->setId(2030)->setName('corp3')->setTicker('C3'); // not updated
        $player1 = (new Player())->setName('p1');
        $char1 = (new Character())->setId(102)->setCorporation($corp1)->setPlayer($player1);
        $member1 = (new CorporationMember())->setId(101)->setCorporation($corp1)
            ->setLogonDate(new \DateTime())->setMissingCharacterMailSent(new \DateTime()); // already sent
        $member2 = (new CorporationMember())->setId(102)->setCorporation($corp1)
            ->setLogonDate(new \DateTime())->setCharacter($char1); // has account
        $member3 = (new CorporationMember())->setId(103)->setCorporation($corp2)
            ->setLogonDate(new \DateTime()); // not in correct corp
        $member4 = (new CorporationMember())->setId(104)->setCorporation($corp1)
            ->setLogonDate(new \DateTime())->setMissingCharacterMailSent(new \DateTime('now -200 days')); // sends mail
        $member5 = (new CorporationMember())->setId(105)->setCorporation($corp1)
            ->setLogonDate(new \DateTime('now -200 days')); // inactive
        $member6 = (new CorporationMember())->setId(106)->setCorporation($corp3)
            ->setLogonDate(new \DateTime()); // corp not updated

        $this->om->persist($token);
        $this->om->persist($active);
        $this->om->persist($days);
        $this->om->persist($corps);
        if (isset($subj)) {
            $this->om->persist($subj);
        }
        $this->om->persist($body);
        $this->om->persist($corp1);
        $this->om->persist($corp2);
        $this->om->persist($corp3);
        $this->om->persist($player1);
        $this->om->persist($char1);
        $this->om->persist($member1);
        $this->om->persist($member2);
        $this->om->persist($member3);
        $this->om->persist($member4);
        $this->om->persist($member5);
        $this->om->persist($member6);
        $this->om->flush();
    }
}
