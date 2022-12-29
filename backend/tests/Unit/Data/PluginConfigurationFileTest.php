<?php /** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\PluginConfigurationFile;
use Neucore\Data\PluginConfigurationURL;
use PHPUnit\Framework\TestCase;

class PluginConfigurationFileTest extends TestCase
{
    public function testConstants()
    {
        $this->assertSame('username', PluginConfigurationFile::PROPERTY_USERNAME);
        $this->assertSame('password', PluginConfigurationFile::PROPERTY_PASSWORD);
        $this->assertSame('email', PluginConfigurationFile::PROPERTY_EMAIL);
        $this->assertSame('status', PluginConfigurationFile::PROPERTY_STATUS);
        $this->assertSame('name', PluginConfigurationFile::PROPERTY_NAME);
    }

    public function testFromArray()
    {
        $obj = PluginConfigurationFile::fromArray([
            'name' => 'name',
            'type' => PluginConfigurationFile::TYPE_SERVICE,
            'phpClass' => 'class',
            'psr4Prefix' => 'prefix',
            'psr4Path' => 'path',
            'oneAccount' => true,
            'properties' => [PluginConfigurationFile::PROPERTY_USERNAME],
            'showPassword' => true,
            'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],
            'directoryName' => 'dir',
            'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
            'textTop' => 'text t',
            'textAccount' => 'text a',
            'textRegister' => 'text r',
            'textPending' => 'text p',
            'configurationData' => 'other: data',
        ]);

        $this->assertSame('name', $obj->name);
        $this->assertSame(PluginConfigurationFile::TYPE_SERVICE, $obj->type);
        $this->assertSame('class', $obj->phpClass);
        $this->assertSame('prefix', $obj->psr4Prefix);
        $this->assertSame('path', $obj->psr4Path);
        $this->assertTrue($obj->oneAccount);
        $this->assertSame([PluginConfigurationFile::PROPERTY_USERNAME], $obj->properties);
        $this->assertTrue($obj->showPassword);
        $this->assertSame([PluginConfigurationFile::ACTION_UPDATE_ACCOUNT], $obj->actions);
        $this->assertSame('dir', $obj->directoryName);
        $this->assertSame(1, count($obj->URLs));
        $this->assertSame('http', $obj->URLs[0]->url);
        $this->assertSame('_blank', $obj->URLs[0]->target);
        $this->assertSame('title', $obj->URLs[0]->title);
        $this->assertSame('text t', $obj->textTop);
        $this->assertSame('text a', $obj->textAccount);
        $this->assertSame('text r', $obj->textRegister);
        $this->assertSame('text p', $obj->textPending);
        $this->assertSame('other: data', $obj->configurationData);
    }

    public function testJsonSerialize()
    {
        $obj = new PluginConfigurationFile();
        $obj->name = 'name';
        $obj->type = PluginConfigurationFile::TYPE_SERVICE;
        $obj->phpClass = 'class';
        $obj->psr4Prefix = 'prefix';
        $obj->psr4Path = 'path';
        $obj->oneAccount = true;
        $obj->properties = [PluginConfigurationFile::PROPERTY_USERNAME];
        $obj->showPassword = true;
        $obj->actions = [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT];
        $obj->directoryName = 'dir';

        $url = new PluginConfigurationURL();
        $url->url = 'http';
        $url->title = 'title';
        $url->target = '_blank';
        $obj->URLs = [$url];
        $obj->textTop = 'text t';
        $obj->textAccount = 'text a';
        $obj->textRegister = 'text r';
        $obj->textPending = 'text p';
        $obj->configurationData = 'other: data';

        $this->assertSame(
            [
                'name' => 'name',
                'type' => PluginConfigurationFile::TYPE_SERVICE,
                'phpClass' => 'class',
                'psr4Prefix' => 'prefix',
                'psr4Path' => 'path',
                'oneAccount' => true,
                'properties' => [PluginConfigurationFile::PROPERTY_USERNAME],
                'showPassword' => true,
                'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],

                'directoryName' => 'dir',
                'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
                'textTop' => 'text t',
                'textAccount' => 'text a',
                'textRegister' => 'text r',
                'textPending' => 'text p',
                'configurationData' => 'other: data',
            ],
            $obj->jsonSerialize()
        );

        $this->assertSame(
            [
                'name' => 'name',
                'type' => PluginConfigurationFile::TYPE_SERVICE,
                'oneAccount' => true,
                'properties' => [PluginConfigurationFile::PROPERTY_USERNAME],
                'showPassword' => true,
                'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],

                'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
                'textTop' => 'text t',
                'textAccount' => 'text a',
                'textRegister' => 'text r',
                'textPending' => 'text p',
                'configurationData' => 'other: data',
            ],
            $obj->jsonSerialize(false)
        );

        $this->assertSame(
            [
                'name' => 'name',
                'type' => PluginConfigurationFile::TYPE_SERVICE,
                'oneAccount' => true,
                'properties' => [PluginConfigurationFile::PROPERTY_USERNAME],
                'showPassword' => true,
                'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],

                'directoryName' => 'dir',
                'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
                'textTop' => 'text t',
                'textAccount' => 'text a',
                'textRegister' => 'text r',
                'textPending' => 'text p',
                'configurationData' => 'other: data',
            ],
            $obj->jsonSerialize(true, false)
        );

        $this->assertSame(
            [
                'name' => 'name',
                'type' => PluginConfigurationFile::TYPE_SERVICE,
                'oneAccount' => true,
                'properties' => [PluginConfigurationFile::PROPERTY_USERNAME],
                'showPassword' => true,
                'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],

                'URLs' => [['url' => 'http', 'title' => 'title', 'target' => '_blank']],
                'textTop' => 'text t',
                'textAccount' => 'text a',
                'textRegister' => 'text r',
                'textPending' => 'text p',
                'configurationData' => 'other: data',
            ],
            $obj->jsonSerialize(false, false)
        );
    }
}
