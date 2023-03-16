<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Traits;

use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Data\EsiErrorLimit;
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

    private Logger $testLogger;

    private SystemVariableStorage $testStorage;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $this->testLogger = new Logger();
        $this->testStorage = new SystemVariableStorage(
            new RepositoryFactory($om),
            new ObjectManager($om, $this->testLogger)
        );
        #apcu_clear_cache();
        #self::$testStorage = new \Neucore\Storage\ApcuStorage();
    }

    public function testCheckForErrors_RateLimit()
    {
        $this->esiRateLimited($this->testStorage, $this->testLogger, true);

        $this->testStorage->set(Variables::ESI_RATE_LIMIT, (string) (time() - 1));
        $this->checkForErrors();
        $this->assertNull($this->getSleepInSeconds());
        $this->assertSame(0, count($this->testLogger->getHandler()->getRecords()));

        $this->testStorage->set(Variables::ESI_RATE_LIMIT, (string) (time() + 10));
        $this->checkForErrors();
        $this->assertLessThanOrEqual(10, $this->getSleepInSeconds());
        $this->assertSame(1, count($this->testLogger->getHandler()->getRecords()));
        $this->assertMatchesRegularExpression(
            '/sleeping \d+ second/',
            $this->testLogger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testCheckForErrors_Throttled()
    {
        $this->esiRateLimited($this->testStorage, $this->testLogger, true);

        $this->testStorage->set(Variables::ESI_THROTTLED, (string)(time() - 5));
        $this->checkForErrors();
        $this->assertNull($this->getSleepInSeconds());
        $this->assertSame(0, count($this->testLogger->getHandler()->getRecords()));

        $this->testStorage->set(Variables::ESI_THROTTLED, (string)(time() + 5));
        $this->checkForErrors();
        $this->assertSame(5, $this->getSleepInSeconds());
        $this->assertMatchesRegularExpression(
            "/EsiRateLimited: hit 'throttled', sleeping \d+ seconds/",
            $this->testLogger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testCheckForErrors_ErrorLimit()
    {
        $this->testStorage->set(Variables::ESI_ERROR_LIMIT, (string)json_encode(new EsiErrorLimit(time(), 9, 20)));

        $this->esiRateLimited($this->testStorage, $this->testLogger, true);

        $this->checkForErrors();

        $this->assertLessThanOrEqual(20, $this->getSleepInSeconds());
        $this->assertStringStartsWith(
            'EsiRateLimited: hit error limit, sleeping ',
            $this->testLogger->getHandler()->getRecords()[0]['message']
        );
        $this->assertStringEndsWith(' seconds', $this->testLogger->getHandler()->getRecords()[0]['message']);
    }
}
