<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Neucore\Plugin\CoreGroup;
use PHPUnit\Framework\TestCase;

class CoreGroupTest extends TestCase
{
    public function testConstruct()
    {
        $group = new CoreGroup(1, 'n');
        $this->assertSame(1, $group->identifier);
        $this->assertSame('n', $group->name);
    }
}
