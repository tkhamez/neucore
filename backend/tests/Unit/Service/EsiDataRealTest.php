<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Monolog\Handler\TestHandler;
use Neucore\Application;
use Neucore\Exception\Exception;
use Neucore\Factory\EveApiFactory;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Middleware\Guzzle\EsiErrorLimit;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Middleware\Guzzle\EsiWarnings;
use Neucore\Service\Character;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use Neucore\Storage\DatabaseStorage;
use PHPUnit\Framework\TestCase;
use Tests\Client;
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
                'use_mail_token_for_unauthenticated_requests' => '0',
            ],
            'guzzle' => [
                'cache' => [
                    'storage' => HttpClientFactory::CACHE_STORAGE_FILESYSTEM,
                    'dir' => $cacheDir,
                ],
                'user_agent' => $settings['env_var_defaults']['NEUCORE_USER_AGENT'],
            ],
        ]);

        $em = $testHelper->getEm();

        $this->log = new Logger();
        $this->log->pushHandler(new TestHandler());

        $repoFactory = new RepositoryFactory($em);
        $om = new ObjectManager($em, $this->log);
        $storage = new DatabaseStorage($repoFactory, $om);

        $this->esiData = new EsiData(
            $this->log,
            new EveApiFactory(
                new HttpClientFactory(
                    $config,
                    new EsiErrorLimit($storage),
                    new EsiWarnings($this->log),
                    new EsiRateLimits($this->log, $storage),
                    new EsiThrottled($storage),
                    $this->log,
                    $em->getConnection(),
                ),
                $config,
                new EveMailToken(
                    $repoFactory,
                    $om,
                    Helper::getAuthenticationProvider(new Client()),
                    $this->log,
                ),
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
        $this->esiData->fetchCharacter(2121766251);
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
            $logMessages[0],
        );
        self::assertStringStartsWith('[404] Client error', $logMessages[1]);
        self::assertStringContainsString('Ensure all IDs are valid before resolving', $logMessages[1]);
    }
}
