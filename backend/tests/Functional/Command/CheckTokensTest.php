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
use Neucore\Factory\RepositoryFactory;
use GuzzleHttp\Psr7\Response;
use Neucore\Service\EsiData;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\Logger;

class CheckTokensTest extends ConsoleTestCase
{
    private Helper $helper;

    private ObjectManager $om;

    private Logger $log;

    private Client $client;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->om = $this->helper->getObjectManager();

        $this->log = new Logger('Test');
        $this->client = new Client();
    }

    /**
     * @throws \Exception
     */
    public function testExecute_UpdateCharNoToken()
    {
        $c = (new Character())->setId(1)->setName('c1')->setCharacterOwnerHash('coh1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);

        $this->client->setResponse(new Response(200, [], '{"name": "char1"}')); // getCharactersCharacterId

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 1: token N/A', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecute_DeleteCharBiomassed()
    {
        $player = (new Player())->setName('p');
        $corp = (new Corporation())->setId(EsiData::CORPORATION_DOOMHEIM_ID);
        $char = (new Character())->setId(3)->setName('char1')->setCorporation($corp)->setPlayer($player);
        $this->om->persist($player);
        $this->om->persist($corp);
        $this->om->persist($char);
        $this->om->flush();

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);

        # read result
        $this->om->clear();
        $repositoryFactory = new RepositoryFactory($this->om);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    /**
     * @throws \Exception
     */
    public function testExecute_ErrorUpdateToken()
    {
        $c = (new Character())->setId(3)->setName('char1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, 123456, 'at', true);

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token parse error', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecute_InvalidToken()
    {
        $c = (new Character())->setId(3)->setName('char1');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, time() - 1000);

        $this->client->setResponse(
            new Response(400, [], '{"error": "invalid_grant"}') // for getAccessToken()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token NOK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    /**
     * @throws \Exception
     */
    public function testExecute_CharacterDeleted()
    {
        // This should not be possible in real scenario because the
        // tokens should already be invalid after a character transfer - right!?.

        $player = (new Player())->setName('p');
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3')->setPlayer($player);
        $this->om->persist($player);
        $this->helper->createOrUpdateEsiToken($c, time() - 60*60, 'at', true);

        list($token) = Helper::generateToken();
        $this->client->setResponse(
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ', 
                "refresh_token": "rt", 
                "expires": '.(time()+60*60).'
            }') // for getAccessToken()
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: character deleted', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);

        # read result
        $this->om->clear();
        $repositoryFactory = new RepositoryFactory($this->om);
        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(0, count($actualChars));
        $removedChar = $repositoryFactory->getRemovedCharacterRepository()->findBy([]);
        $this->assertSame(1, count($removedChar));
    }

    /**
     * @throws \Exception
     */
    public function testExecute_ValidToken()
    {
        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, time() - 60*60, 'at', true);

        list($token) = Helper::generateToken(['scope1', 'scope2'], 'Name', 'coh3');
        $this->client->setResponse(
            new Response( // for getAccessToken()
                200,
                [],
                '{"access_token": '.json_encode($token).', "refresh_token": "rt", "expires": '.(time()+60*60).'}'
            ),
        );

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);

        # read result
        $this->om->clear();

        $repositoryFactory = new RepositoryFactory($this->om);

        $actualChars = $repositoryFactory->getCharacterRepository()->findBy([]);
        $this->assertSame(1, count($actualChars));
        $this->assertSame(3, $actualChars[0]->getId());
        $this->assertSame('char1', $actualChars[0]->getName());
        $this->assertNull($actualChars[0]->getLastUpdate()); // token check does not change the update date
        $nameChange = $repositoryFactory->getCharacterNameChangeRepository()->findBy([]);
        $this->assertSame(0, count($nameChange)); // change via ESI token is no longer recorded
    }

    /**
     * @throws \Exception
     */
    public function testExecute_ValidTokenUnexpectedData()
    {
        list($token) = Helper::generateToken(['scope1', 'scope2'], 'Name', 'coh3', 123, 'invalid');

        $c = (new Character())->setId(3)->setName('char1')->setCharacterOwnerHash('coh3');
        $this->helper->addNewPlayerToCharacterAndFlush($c);
        $this->helper->createOrUpdateEsiToken($c, 123456, $token, true);

        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0], [
            ClientInterface::class => $this->client,
            LoggerInterface::class => $this->log,
        ]);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: all)', $actual[0]);
        $this->assertStringEndsWith('  Character 3: token OK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);
        $this->assertSame(
            'Unexpected JWT data, missing character owner hash.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testExecute_ActiveChars()
    {
        $this->setupActive();

        // Note: None of the tokens are valid so this test does not need the test client.
        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0, '--characters' => 'active']);

        $actual = explode("\n", $output);
        $this->assertSame(7, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: active)', $actual[0]);
        $this->assertStringEndsWith('  Character 100101: token NOK', $actual[1]);
        $this->assertStringEndsWith('  Character 100102: token N/A', $actual[2]);
        $this->assertStringEndsWith('  Character 200101: token NOK', $actual[3]);
        $this->assertStringEndsWith('  Character 300101: token NOK', $actual[4]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[5]);
        $this->assertSame('', $actual[6]);
    }

    public function testExecute_OtherChars()
    {
        $this->setupActive();

        // Note: None of the tokens are valid so this test does not need the test client.
        $output = $this->runConsoleApp('check-tokens', ['--sleep' => 0, '--characters' => 'other']);

        $actual = explode("\n", $output);
        $this->assertSame(4, count($actual));
        $this->assertStringEndsWith('Started "check-tokens" (characters: other)', $actual[0]);
        $this->assertStringEndsWith('  Character 400101: token NOK', $actual[1]);
        $this->assertStringEndsWith('Finished "check-tokens"', $actual[2]);
        $this->assertSame('', $actual[3]);
    }

    private function setupActive()
    {
        $alliance = (new Alliance())->setId(101)->setName('Alliance 1');
        $corporation1 = (new Corporation())->setId(1001)->setName('Corp 1')->setAlliance($alliance);
        $corporation2 = (new Corporation())->setId(2001)->setName('Corp 3');
        $this->om->persist($alliance);
        $this->om->persist($corporation1);
        $this->om->persist($corporation2);

        $setting1 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('101');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('1001,2001');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ACTIVE_DAYS))->setValue('30');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);

        $main1 = $this->helper->addCharacterMain('Main 1', 100101)->setCorporation($corporation1);
        $this->helper->addCharacterToPlayer('Alt 1', 100102, $main1->getPlayer());
        $this->helper->addCharacterMain('Main 2', 200101)->setCorporation($corporation2);
        $this->helper->addCharacterMain('Main 3', 300101, [], [], true, new \DateTime('now -7 days'));
        $this->helper->addCharacterMain('Main 4', 400101);
    }
}
