<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Traits;

use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Storage\Variables;
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiRateLimitedTest extends TestCase
{
    use EsiRateLimited;

    /**
     * @var Logger
     */
    private $testLogger;

    /**
     * @var SystemVariableStorage
     */
    private $testStorage;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $this->testLogger = new Logger('Test');
        $this->testStorage = new SystemVariableStorage(
            new RepositoryFactory($om),
            new ObjectManager($om, $this->testLogger)
        );
        #apcu_clear_cache();
        #self::$testStorage = new \Neucore\Storage\ApcuStorage();
    }

    public function testCheckForErrors_Throttled()
    {
        $this->testStorage->set(Variables::ESI_THROTTLED, '1');

        $this->esiRateLimited($this->testStorage, $this->testLogger, true);

        $this->checkForErrors();

        $this->assertSame(60, $this->getSleepInSeconds());
        $this->assertSame(
            'EsiRateLimited: hit "throttled", sleeping 60 seconds',
            $this->testLogger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testCheckForErrors_ErrorLimit()
    {
        $this->testStorage->set(Variables::ESI_ERROR_LIMIT, (string) \json_encode([
            'updated' => time(),
            'remain' => 9,
            'reset' => 20,
        ]));

        $this->esiRateLimited($this->testStorage, $this->testLogger, true);

        $this->checkForErrors();

        $this->assertGreaterThanOrEqual(20, $this->getSleepInSeconds());
        $this->assertStringStartsWith(
            'EsiRateLimited: hit error limit, sleeping ',
            $this->testLogger->getHandler()->getRecords()[0]['message']
        );
        $this->assertStringEndsWith(' seconds', $this->testLogger->getHandler()->getRecords()[0]['message']);
    }
}
