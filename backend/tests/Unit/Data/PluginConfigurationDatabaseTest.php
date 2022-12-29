<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Data\PluginConfigurationURL;
use PHPUnit\Framework\TestCase;

class PluginConfigurationDatabaseTest extends TestCase
{
    public function testJsonSerialize()
    {
        $configuration = new PluginConfigurationDatabase();
        $configuration->active = false;
        $configuration->requiredGroups = [1, 2];
        $configuration->directoryName = 'plugin-dir';
        $url = new PluginConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';
        $configuration->URLs = [$url];
        $configuration->textTop = 'text t';
        $configuration->textAccount = 'text a';
        $configuration->textRegister = 'text r';
        $configuration->textPending = 'text p';
        $configuration->configurationData = 'other: data';

        $actual1 = $configuration->jsonSerialize();
        $this->assertSame([
            'active' => false,
            'requiredGroups' => [1, 2],
            'directoryName' => 'plugin-dir',
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ], $actual1);

        $actual2 = $configuration->jsonSerialize(false);
        $this->assertSame([
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ], $actual2);


        $actual3 = $configuration->jsonSerialize(true, false);
        $this->assertSame([
            'active' => false,
            'requiredGroups' => [1, 2],
            'directoryName' => 'plugin-dir',
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ], $actual3);
    }

    public function testFromArray()
    {
        $data = [
            'active' => false,
            'requiredGroups' => [1, 2],
            'directoryName' => 'plugin-dir',
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ];

        $actual = PluginConfigurationDatabase::fromArray($data);

        $this->assertSame(false, $actual->active);
        $this->assertSame([1, 2], $actual->requiredGroups);
        $this->assertSame('plugin-dir', $actual->directoryName);
        $this->assertSame(1, count($actual->URLs));
        $this->assertSame('http', $actual->URLs[0]->url);
        $this->assertSame('_blank', $actual->URLs[0]->target);
        $this->assertSame('title', $actual->URLs[0]->title);
        $this->assertSame('text t', $actual->textTop);
        $this->assertSame('text a', $actual->textAccount);
        $this->assertSame('text r', $actual->textRegister);
        $this->assertSame('text p', $actual->textPending);
        $this->assertSame('other: data', $actual->configurationData);
    }
}
