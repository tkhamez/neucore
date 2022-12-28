<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Service;
use Neucore\Data\ServiceConfiguration;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function testJsonSerialize()
    {
        $service = new Service();
        $service->setName('s1');
        $service->setConfiguration(new ServiceConfiguration());

        $this->assertSame(
            ['id' => 0, 'name' => 's1'],
            json_decode((string) json_encode($service), true)
        );
        $this->assertSame(
            ['id' => 0, 'name' => 's1', 'configuration' => [
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
            ]],
            $service->jsonSerialize(false)
        );
        $this->assertSame(
            ['id' => 0, 'name' => 's1', 'configuration' => [
                'name' => '',
                'type' => '',
                'directoryName' => '',
                'active' => false,
                'requiredGroups' => [],
                'phpClass' => '',
                'psr4Prefix' => '',
                'psr4Path' => '',
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
            ]],
            $service->jsonSerialize(false, true)
        );
    }

    public function testGetId()
    {
        $this->assertSame(0, (new Service())->getId());
    }

    public function testSetGetName()
    {
        $service = new Service();
        $this->assertSame('', $service->getName());
        $this->assertSame('name',  $service->setName('name')->getName());
    }

    public function testSetGetConfiguration()
    {
        $service = new Service();
        $data = new ServiceConfiguration();

        $this->assertNotSame($data, $service->getConfiguration());
        $this->assertEquals($data, $service->getConfiguration());

        $data->name = 'name';
        $data->type = 'service';
        $data->phpClass = 'class';
        $data->psr4Prefix = 'prefix';
        $data->psr4Path = 'path';
        $data->oneAccount = true;
        $data->requiredGroups = [1, 2];
        $data->properties = ['username', 'status'];
        $data->showPassword = true;
        $data->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $data->URLs = [];
        $data->textAccount = 'text a';
        $data->textTop = 'text t';
        $data->textRegister = 'text r';
        $data->textPending = 'text p';
        $data->configurationData = 'other: data';
        $this->assertNotSame($data, $service->setConfiguration($data)->getConfiguration());
        $this->assertEquals($data, $service->setConfiguration($data)->getConfiguration());
        $this->assertSame(
            $data->jsonSerialize(),
            $service->setConfiguration($data)->getConfiguration()->jsonSerialize()
        );
    }
}
