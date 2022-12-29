<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\PluginConfigurationURL;
use PHPUnit\Framework\TestCase;

class PluginConfigurationURLTest extends TestCase
{
    public function testJsonSerialize()
    {
        $url = new PluginConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';

        $this->assertSame(
            ['url' => 'http', 'title' => 'title', 'target' => '_blank'],
            $url->jsonSerialize()
        );
    }
}
