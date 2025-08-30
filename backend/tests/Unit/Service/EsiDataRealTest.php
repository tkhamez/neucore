<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Monolog\Handler\TestHandler;
use Neucore\Application;
use Neucore\Exception\Exception;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\Character;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiDataRealTest extends TestCase
{
    private Logger $log;

    private EsiData $esiData;

    protected function setUp(): void
    {
        $this->markTestSkipped('This test uses the real ESI API.');

        $testHelper = new Helper();

        $settings = Application::loadFile('settings.php');
        $cacheDir = Application::ROOT_DIR . '/var/cache/test';
        $testHelper->deleteDirectory($cacheDir);

        $config = new Config([
            'eve' => [
                'esi_host' => $settings['eve']['esi_host'],
                'esi_compatibility_date' => $settings['eve']['esi_compatibility_date'],
            ],
            'guzzle' => [
                'cache' => ['dir' => $cacheDir],
                'user_agent' => $settings['env_var_defaults']['NEUCORE_USER_AGENT'],
            ],
        ]);

        $em = $testHelper->getEm();

        $this->log = new Logger();
        $this->log->pushHandler(new TestHandler());

        $repoFactory = new RepositoryFactory($em);
        $om = new ObjectManager($em, $this->log);
        $storage = new SystemVariableStorage($repoFactory, $om);

        $this->esiData = new EsiData(
            $this->log,
            new EsiApiFactory(
                new HttpClientFactory(
                    $config,
                    new EsiHeaders($this->log, $storage),
                    new Esi429Response($this->log, $storage),
                    $this->log,
                ),
                $config,
            ),
            $om,
            $repoFactory,
            new Character($om, $repoFactory),
        );
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_NotFound(): void
    {
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Character not found (exception)');
        $this->esiData->fetchCharacter(10);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_Deleted(): void
    {
        $this->expectExceptionCode(410);
        $this->expectExceptionMessage('Character has been deleted (exception)');
        $this->esiData->fetchCharacter(2112148223);
    }

    /**
     * @throws Exception
     */
    public function testFetchCharacter_Found(): void
    {
        $char = $this->esiData->fetchCharacter(96061222);
        self::assertSame('Tian Khamez', $char->getName());
    }

    public function testFetchUniverseNames(): void
    {
        // 0 and 2 are valid, 1 is invalid.
        $result = $this->esiData->fetchUniverseNames([0, 1, 2], 2);
        $logMessages = $this->log->getMessages();

        self::assertSame(2, count($result));
        self::assertSame(2, count($logMessages));
        self::assertSame(
            'fetchUniverseNames: Invalid ID(s) in request, trying again with max. 1 IDs.',
            $logMessages[0]
        );
        self::assertStringStartsWith('[404] Client error', $logMessages[1]);
        self::assertStringContainsString('Ensure all IDs are valid before resolving', $logMessages[1]);
    }
}
