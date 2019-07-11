<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Corporation;
use Neucore\Entity\SystemVariable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class UpdateMemberTrackingTest extends ConsoleTestCase
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();
        $this->client = new Client();
    }

    public function testExecuteErrorChar()
    {
        $director = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1));
        $this->em->persist($director);
        $this->em->flush();

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Error obtaining character data from director_char_1', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteErrorCorp()
    {
        $director = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{"corporation_id": 1}');
        $this->em->persist($director);
        $this->em->flush();

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Corporation not found for director_char_1', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteErrorToken()
    {
        $director = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))->setValue('{"corporation_id": 1}');
        $corp = (new Corporation())->setId(1);
        $this->em->persist($director);
        $this->em->persist($corp);
        $this->em->flush();

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Error refreshing token for director_char_1', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteErrorData()
    {
        $director = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))
            ->setValue('{"corporation_id": 1, "character_id": 10}');
        $corp = (new Corporation())->setId(1);
        $token = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1))
            ->setValue('{"access": "at", "refresh": "rt", "expires": '. (time() + 60*20).'}');
        $this->em->persist($director);
        $this->em->persist($corp);
        $this->em->persist($token);
        $this->em->flush();

        $this->client->setResponse(new Response(500));

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => new Logger('test') // ignore the log entry
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Error getting member tracking data from ESI for director_char_1', $actual[1]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[2]);
        $this->assertStringEndsWith('', $actual[3]);
    }

    public function testExecuteSuccess()
    {
        $director1 = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 1))
            ->setValue('{"corporation_id": 1, "character_id": 10}');
        $director2 = (new SystemVariable(SystemVariable::DIRECTOR_CHAR . 2))
            ->setValue('{"corporation_id": 2, "character_id": 11}');
        $token1 = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 1))
            ->setValue('{"access": "at", "refresh": "rt", "expires": '. (time() + 60*20).'}');
        $token2 = (new SystemVariable(SystemVariable::DIRECTOR_TOKEN . 2))
            ->setValue('{"access": "at", "refresh": "rt", "expires": '. (time() + 60*20).'}');
        $corp1 = (new Corporation())->setId(1);
        $corp2 = (new Corporation())->setId(2);
        $this->em->persist($director1);
        $this->em->persist($director2);
        $this->em->persist($token1);
        $this->em->persist($token2);
        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->flush();

        $this->client->setResponse(
            new Response(200, [], '[{"character_id": 100}]'), // corporations/1/membertracking/
            new Response(200, [], '[{"category": "character", "id": "100", "name": "Paul"}]'), // universe/names/
            new Response(200, [], '[]') // corporations/1/membertracking/
        );

        $output = $this->runConsoleApp('update-member-tracking', ['--sleep' => 0], [
            ClientInterface::class => $this->client
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(5, count($actual));
        $this->assertStringEndsWith('Started "update-member-tracking"', $actual[0]);
        $this->assertStringEndsWith('  Updated tracking data for 1 members of corporation 1', $actual[1]);
        $this->assertStringEndsWith('  Updated tracking data for 0 members of corporation 2', $actual[2]);
        $this->assertStringEndsWith('Finished "update-member-tracking"', $actual[3]);
        $this->assertStringEndsWith('', $actual[4]);
    }
}
