<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\ServiceConfigurationURL;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationURLTest extends TestCase
{
    public function testJsonSerialize()
    {
        $url = new ServiceConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';

        $this->assertSame(
            ['url' => 'http', 'title' => 'title', 'target' => '_blank'],
            $url->jsonSerialize()
        );
    }
}
