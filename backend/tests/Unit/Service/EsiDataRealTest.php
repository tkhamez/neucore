<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Monolog\Handler\TestHandler;
use Neucore\Application;
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
    private static Helper $testHelper;

    private static EsiData $esiData;

    private static string $cacheDir;

    public static function setUpBeforeClass(): void
    {
        $settings = Application::loadFile('settings.php');
        self::$cacheDir = Application::ROOT_DIR . '/var/cache/test';
        $config = new Config([
            'eve' => [
                'esi_host' => $settings['eve']['esi_host'],
                'esi_compatibility_date' => $settings['eve']['esi_compatibility_date'],
            ],
            'guzzle' => [
                'cache' => ['dir' => self::$cacheDir],
                'user_agent' => $settings['env_var_defaults']['NEUCORE_USER_AGENT'],
            ],
        ]);

        self::$testHelper = new Helper();
        $em = self::$testHelper->getEm();

        $log = new Logger();
        $log->pushHandler(new TestHandler());

        $repoFactory = new RepositoryFactory($em);
        $om = new ObjectManager($em, $log);
        $storage = new SystemVariableStorage($repoFactory, $om);

        self::$esiData = new EsiData(
            $log,
            new EsiApiFactory(
                new HttpClientFactory(
                    $config,
                    new EsiHeaders($log, $storage),
                    new Esi429Response($log, $storage),
                    $log,
                ),
                $config,
            ),
            $om,
            $repoFactory,
            new Character($om, $repoFactory),
        );
    }

    protected function setUp(): void
    {
        self::$testHelper->deleteDirectory(self::$cacheDir);

        $this->markTestSkipped('This test uses the real ESI API.');
    }

    public function testFetchCharacter_NotFound(): void
    {
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Character not found (exception)');
        self::$esiData->fetchCharacter(10);
    }

    public function testFetchCharacter_Deleted(): void
    {
        $this->expectExceptionCode(410);
        $this->expectExceptionMessage('Character has been deleted (exception)');
        self::$esiData->fetchCharacter(2112148223);
    }

    public function testFetchCharacter_Found(): void
    {
        $char = self::$esiData->fetchCharacter(96061222);
        $this->assertSame('Tian Khamez', $char->getName());
    }
}
