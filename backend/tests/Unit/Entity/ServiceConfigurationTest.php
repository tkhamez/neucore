<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\ServiceConfiguration;
use Neucore\Entity\ServiceConfigurationURL;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase
{
    public function testJsonSerialize_FromArray()
    {
        $configuration = new ServiceConfiguration();
        $configuration->phpClass = 'class';
        $configuration->psr4Prefix = 'prefix';
        $configuration->psr4Path = 'path';
        $configuration->requiredGroups = [1, 2];
        $configuration->properties = ['username', 'status'];
        $configuration->showPassword = true;
        $configuration->actions = ['update-account'];
        $url = new ServiceConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';
        $configuration->URLs = [$url];
        $configuration->textAccount = 'text a';
        $configuration->textTop = 'text t';
        $configuration->textRegister = 'text r';
        $configuration->textPending = 'text p';
        $configuration->configurationData = 'other: data';

        $array = $configuration->jsonSerialize();

        $this->assertSame([
            'phpClass' => 'class',
            'psr4Prefix' => 'prefix',
            'psr4Path' => 'path',
            'requiredGroups' => [1, 2],
            'properties' => ['username', 'status'],
            'showPassword' => true,
            'actions' => ['update-account'],
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textAccount' => 'text a',
            'textTop' => 'text t',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ], $array);

        $result = ServiceConfiguration::fromArray($array);

        $this->assertNotSame($configuration, $result);
        $this->assertEquals($configuration, $result);
    }
}
