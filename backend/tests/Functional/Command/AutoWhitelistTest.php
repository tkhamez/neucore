<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class AutoWhitelistTest extends ConsoleTestCase
{

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
    }

    /**
     * @throws \Exception
     */
    public function testExecute()
    {
        $this->markTestIncomplete('TODO'); # TODO
    }
}
