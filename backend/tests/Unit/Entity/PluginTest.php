<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Data\PluginConfigurationFile;
use Neucore\Data\PluginConfigurationURL;
use Neucore\Entity\Plugin;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Plugin\PluginConfiguration;
use PHPUnit\Framework\TestCase;
use Tests\Logger;

class PluginTest extends TestCase
{
    public function testJsonSerialize()
    {
        $plugin = new Plugin();
        $plugin->setName('s1');
        $plugin->setConfigurationDatabase(new PluginConfigurationDatabase());
        $plugin->setConfigurationFile(new PluginConfigurationFile());

        $this->assertSame(
            ['id' => 0, 'name' => 's1'],
            json_decode((string) json_encode($plugin), true)
        );

        $this->assertSame(
            [
                'id' => 0,
                'name' => 's1',
                'configurationDatabase' => [
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
                'configurationFile' => [
                    'name' => '',
                    'type' => '',
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [],
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
            ],
            $plugin->jsonSerialize(false)
        );

        $this->assertSame(
            [
                'id' => 0,
                'name' => 's1',
                'configurationDatabase' => [
                    'active' => false,
                    'requiredGroups' => [],
                    'directoryName' => '',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
                'configurationFile' => [
                    'name' => '',
                    'type' => '',
                    'phpClass' => '',
                    'psr4Prefix' => '',
                    'psr4Path' => '',
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [],
                    'directoryName' => '',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
            ],
            $plugin->jsonSerialize(false, true)
        );

        $this->assertSame(
            [
                'id' => 0,
                'name' => 's1',
                'configurationDatabase' => [
                    'active' => false,
                    'requiredGroups' => [],
                    'directoryName' => '',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
                'configurationFile' => [
                    'name' => '',
                    'type' => '',
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [],
                    'directoryName' => '',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
            ],
            $plugin->jsonSerialize(false, true, false)
        );
    }

    public function testGetId()
    {
        $this->assertSame(0, (new Plugin())->getId());
    }

    public function testSetGetName()
    {
        $plugin = new Plugin();
        $this->assertSame('', $plugin->getName());
        $this->assertSame('name',  $plugin->setName('name')->getName());
    }

    public function testSetGetConfigurationDatabase()
    {
        $data = new PluginConfigurationDatabase();
        $data->active = true;
        $data->requiredGroups = [1, 2];
        $data->directoryName = 'plugin';
        $data->URLs = [];
        $data->textTop = 'text t';
        $data->textAccount = 'text a';
        $data->textRegister = 'text r';
        $data->textPending = 'text p';
        $data->configurationData = 'other: data';

        $expected = [
            'active' => $data->active,
            'requiredGroups' => $data->requiredGroups,
            'directoryName' => $data->directoryName,
            'URLs' => array_map(function (PluginConfigurationURL $item) {
                return $item->jsonSerialize();
            }, $data->URLs),
            'textTop' => $data->textTop,
            'textAccount' => $data->textAccount,
            'textRegister' => $data->textRegister,
            'textPending' => $data->textPending,
            'configurationData' => $data->configurationData,
        ];

        $this->assertSame(
            $expected,
            (new Plugin())->setConfigurationDatabase($data)->getConfigurationDatabase()?->jsonSerialize()
        );
    }

    public function testSetGetConfigurationFile()
    {
        $data = new PluginConfigurationFile();
        $data->name = 'name';

        $actual = (new Plugin())->setConfigurationFile($data)->getConfigurationFile();

        $this->assertSame($data, $actual);
    }

    public function testSetGetServiceImplementation()
    {
        $impl = new ServiceTest_ServiceImplementation(new Logger(''), new PluginConfiguration(1, [], ''));
        $actual = (new Plugin())->setServiceImplementation($impl)->getServiceImplementation();
        $this->assertSame($impl, $actual);
    }
}
