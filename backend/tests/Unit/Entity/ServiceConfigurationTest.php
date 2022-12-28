<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Data\ServiceConfiguration;
use Neucore\Data\ServiceConfigurationURL;
use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase
{
    public function testJsonSerialize_FromArray()
    {
        $configuration = new ServiceConfiguration();
        $configuration->name = 'Test';
        $configuration->type = ServiceConfiguration::TYPE_SERVICE;
        $configuration->directoryName = 'plugin-dir';
        $configuration->active = false;
        $configuration->requiredGroups = [1, 2];
        $configuration->phpClass = 'class';
        $configuration->psr4Prefix = 'prefix';
        $configuration->psr4Path = 'path';
        $configuration->oneAccount = true;
        $configuration->properties = ['username', 'status'];
        $configuration->showPassword = true;
        $configuration->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $url = new ServiceConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';
        $configuration->URLs = [$url];
        $configuration->textTop = 'text t';
        $configuration->textAccount = 'text a';
        $configuration->textRegister = 'text r';
        $configuration->textPending = 'text p';
        $configuration->configurationData = 'other: data';

        $array = $configuration->jsonSerialize();

        $this->assertSame([
            'name' => 'Test',
            'type' => ServiceConfiguration::TYPE_SERVICE,
            'directoryName' => 'plugin-dir',
            'active' => false,
            'requiredGroups' => [1, 2],
            'phpClass' => 'class',
            'psr4Prefix' => 'prefix',
            'psr4Path' => 'path',
            'oneAccount' => true,
            'properties' => ['username', 'status'],
            'showPassword' => true,
            'actions' => [ServiceConfiguration::ACTION_UPDATE_ACCOUNT],
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ], $array);

        $result = ServiceConfiguration::fromArray($array);

        $this->assertNotSame($configuration, $result);
        $this->assertEquals($configuration, $result);
    }
}
