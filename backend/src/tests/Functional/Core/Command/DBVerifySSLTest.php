<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Tests\Functional\ConsoleTestCase;

class DBVerifySSLTest extends ConsoleTestCase
{
    public function testExecute()
    {
        $output = $this->runConsoleApp('db-verify-ssl');
        $actual = explode("\n", $output);

        $this->assertStringStartsWith('Ssl_cipher: ', $actual[0]);
        $this->assertSame('', $actual[1]);
        $this->assertSame(2, count($actual));
    }
}
