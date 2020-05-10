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

    public function testCheckErrorLimit()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $storage = new SystemVariableStorage(new RepositoryFactory($om), new ObjectManager($om, new Logger('Test')));
        #apcu_clear_cache();
        #$storage = new \Neucore\Storage\ApcuStorage();

        $this->esiRateLimited($storage, true);

        $storage->set(Variables::ESI_ERROR_LIMIT, (string) \json_encode([
            'updated' => time(),
            'remain' => 9,
            'reset' => 20,
        ]));

        $this->checkErrorLimit();

        $this->assertGreaterThanOrEqual(20, $this->getSleepInSeconds());
    }
}
