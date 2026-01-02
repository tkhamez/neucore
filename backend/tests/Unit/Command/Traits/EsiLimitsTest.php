<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Traits;

use Monolog\Handler\TestHandler;
use Neucore\Command\Traits\EsiLimits;
use Neucore\Data\EsiErrorLimit;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Storage\Variables;
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiLimitsTest extends TestCase
{
    use EsiLimits;

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
            new ObjectManager($om, $this->testLogger),
        );
        #apcu_clear_cache();
        #self::$testStorage = new \Neucore\Storage\ApcuStorage();
    }

    public function testCheckForErrors_RateLimited(): void
    {

        $this->esiLimits($this->testStorage, $this->testLogger, true);

        $this->testStorage->set(Variables::ESI_RATE_LIMITED, (string) (time() - 1));
        $this->checkLimits();
        self::assertNull($this->getSleepInSeconds());
        self::assertInstanceOf(TestHandler::class, $this->testLogger->getHandler());
        self::assertSame(0, count($this->testLogger->getHandler()->getRecords()));

        $this->testStorage->set(Variables::ESI_RATE_LIMITED, (string) (time() + 10));
        $this->checkLimits();
        self::assertLessThanOrEqual(10, $this->getSleepInSeconds());
        self::assertInstanceOf(TestHandler::class, $this->testLogger->getHandler());
        self::assertSame(1, count($this->testLogger->getHandler()->getRecords()));
        self::assertMatchesRegularExpression(
            '/sleeping \d+ second/',
            $this->testLogger->getMessages()[0],
        );
    }

    public function testCheckForErrors_Throttled(): void
    {
        $this->esiLimits($this->testStorage, $this->testLogger, true);

        $this->testStorage->set(Variables::ESI_THROTTLED, (string) (time() - 5));
        $this->checkLimits();
        self::assertNull($this->getSleepInSeconds());
        self::assertInstanceOf(TestHandler::class, $this->testLogger->getHandler());
        self::assertSame(0, count($this->testLogger->getHandler()->getRecords()));

        $this->testStorage->set(Variables::ESI_THROTTLED, (string) (time() + 5));
        $this->checkLimits();
        self::assertGreaterThanOrEqual(4, $this->getSleepInSeconds()); // very rarely this is 4
        self::assertLessThanOrEqual(5, $this->getSleepInSeconds());
        self::assertMatchesRegularExpression(
            "/EsiRateLimited: hit 'throttled', sleeping \d+ seconds/",
            $this->testLogger->getMessages()[0],
        );
    }

    public function testCheckForErrors_ErrorLimit(): void
    {
        $this->testStorage->set(
            Variables::ESI_ERROR_LIMIT,
            (string) json_encode(new EsiErrorLimit(time(), 9, 20)),
        );

        $this->esiLimits($this->testStorage, $this->testLogger, true);

        $this->checkLimits();

        self::assertLessThanOrEqual(20, $this->getSleepInSeconds());
        self::assertStringStartsWith(
            'EsiRateLimited: hit error limit, sleeping ',
            $this->testLogger->getMessages()[0],
        );
        self::assertStringEndsWith(' seconds', $this->testLogger->getMessages()[0]);
    }
}
