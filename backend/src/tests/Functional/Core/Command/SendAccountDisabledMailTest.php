<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class SendAccountDisabledMailTest extends ConsoleTestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();
        $this->client = new Client();
        $this->repoFactory = new RepositoryFactory($this->em);
    }

    public function testExecuteNotActive()
    {
        $output = $this->runConsoleApp('send-account-disabled-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "send-account-disabled-mail"', $actual[0]);
        $this->assertStringEndsWith('"Deactivate Accounts" settings is not enabled.', $actual[1]);
        $this->assertStringEndsWith('* Finished "send-account-disabled-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteMisconfiguration()
    {
        $deactivateAccounts = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $active = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $alliances = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('1010');
        $alliance = (new Alliance())->setId(1010)->setName('alli')->setTicker('A');
        $corp = (new Corporation())->setId(2020)->setName('corp')->setTicker('C')->setAlliance($alliance);
        $player = (new Player())->setName('play');
        $char = (new Character())->setId(30)->setName('c3')->setPlayer($player)->setCorporation($corp);
        $this->em->persist($deactivateAccounts);
        $this->em->persist($active);
        $this->em->persist($alliances);
        $this->em->persist($alliance);
        $this->em->persist($corp);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();

        $output = $this->runConsoleApp('send-account-disabled-mail', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "send-account-disabled-mail"', $actual[0]);
        $this->assertStringEndsWith('Missing character that can send mails.', $actual[1]);
        $this->assertStringEndsWith('* Finished "send-account-disabled-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecute()
    {
        $deactivateAccounts = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $active = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $alliances = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('1010');
        $token = (new SystemVariable(SystemVariable::MAIL_TOKEN))
            ->setValue('{"id": 90, "access": "abc", "refresh": "", "expires": ""}');
        $subj = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT))->setValue('subj');
        $body = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY))->setValue('text');
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
        $this->em->persist($deactivateAccounts);
        $this->em->persist($active);
        $this->em->persist($alliances);
        $this->em->persist($token);
        $this->em->persist($subj);
        $this->em->persist($body);
        $this->em->persist($alliance);
        $this->em->persist($corp);
        $this->em->persist($p1);
        $this->em->persist($p2);
        $this->em->persist($p3);
        $this->em->persist($p4);
        $this->em->persist($p5);
        $this->em->persist($c2);
        $this->em->persist($c3);
        $this->em->persist($c4);
        $this->em->persist($c5);
        $this->em->flush();

        $this->client->setResponse(new Response(200, [], '373515628'));

        $output = $this->runConsoleApp('send-account-disabled-mail', ['--sleep' => 0], [
            ClientInterface::class => $this->client
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('* Started "send-account-disabled-mail"', $actual[0]);
        $this->assertStringEndsWith('Mail sent to 30', $actual[1]);
        $this->assertStringEndsWith('* Finished "send-account-disabled-mail"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }
}
