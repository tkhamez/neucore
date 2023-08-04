<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Util\Database;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class DBVerifySSLTest extends ConsoleTestCase
{
    public function testExecute()
    {
        if (Database::getDbName((new Helper())->getEm()) === 'sqlite') {
            $this->markTestSkipped('Does not apply to SQLite.');
        }

        $output = $this->runConsoleApp('db-verify-ssl');
        $actual = explode("\n", $output);

        $this->assertStringStartsWith('Ssl_cipher: ', $actual[0]);
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));
    }
}
