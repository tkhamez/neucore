<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Data\ServiceConfiguration;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\ServiceRepository;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;

class ServiceAdminControllerTest extends WebTestCase
{
    private Helper $helper;

    private ServiceRepository $repository;

    private int $serviceId;

    private Logger $log;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->repository = RepositoryFactory::getInstance($this->helper->getObjectManager())->getServiceRepository();
        $this->log = new Logger('test');

        $_SESSION = null;
        $this->setupDb();
    }

    protected function tearDown(): void
    {
        unset($_ENV['NEUCORE_PLUGINS_INSTALL_DIR']);
    }

    public function testList403()
    {
        $response1 = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);

        $response2 = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testList200()
    {
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([['id' => $this->serviceId, 'name' => 'S1']], $this->parseJsonBody($response));
    }

    public function testConfigurations403()
    {
        $response1 = $this->runApp('GET', '/api/user/service-admin/configurations');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);

        $response2 = $this->runApp('GET', '/api/user/service-admin/configurations');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testConfigurations500()
    {
        $this->loginUser(1);

        $pluginBaseDir = __DIR__ . '/ServiceAdminController/Error';

        $response = $this->runApp(
            'GET',
            '/api/user/service-admin/configurations',
            null,
            null,
            [LoggerInterface::class => $this->log], // ignore log
            [['NEUCORE_PLUGINS_INSTALL_DIR',  $pluginBaseDir]],
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testConfigurations200_EmptyBasePath()
    {
        $this->loginUser(1);

        $response = $this->runApp(
            'GET',
            '/api/user/service-admin/configurations',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', '']],
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $this->parseJsonBody($response));
    }

    public function testConfigurations200()
    {
        $this->loginUser(1);

        $response = $this->runApp(
            'GET',
            '/api/user/service-admin/configurations',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceAdminController/OK']],
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([[
            'name' => 'Test',
            'type' => ServiceConfiguration::TYPE_SERVICE,
            'directoryName' => 'plugin-name',
            'active' => false,
            'requiredGroups' => [],
            'phpClass' => 'Vendor\Neucore\Plugin\Name\Service',
            'psr4Prefix' => 'Vendor\Neucore\Plugin\Name',
            'psr4Path' => 'src',
            'oneAccount' => true,
            'properties' => ['username'],
            'showPassword' => true,
            'actions' => ['update-account'],
            'URLs' => [],
            'textTop' => '',
            'textAccount' => '',
            'textRegister' => '',
            'textPending' => '',
            'configurationData' => '',
        ]], $this->parseJsonBody($response));
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/service-admin/create');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(2);

        $response = $this->runApp('POST', '/api/user/service-admin/create');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/service-admin/create', ['name' => ''], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreate201()
    {
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/service-admin/create', ['name' => 'New Service'], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $this->assertEquals(201, $response->getStatusCode());

        $services = $this->repository->findBy(['name' => 'New Service']);
        $this->assertSame(1, count($services));
        $this->assertSame('New Service', $services[0]->getName());
        $this->assertSame(
            ['id' => $services[0]->getId(), 'name' => 'New Service'],
            $this->parseJsonBody($response)
        );
    }

    public function testRename403()
    {
        $response = $this->runApp('PUT', '/api/user/service-admin/1/rename');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(2);

        $response = $this->runApp('PUT', '/api/user/service-admin/1/rename');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename404()
    {
        $this->loginUser(1);

        $response = $this->runApp('PUT', '/api/user/service-admin/'.($this->serviceId+99).'/rename');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service-admin/$this->serviceId/rename",
            ['name' => ''],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service-admin/$this->serviceId/rename",
            ['name' => 'Renamed Service'],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => $this->serviceId, 'name' => 'Renamed Service'],
            $this->parseJsonBody($response)
        );

        $service = $this->repository->find($this->serviceId);
        $this->assertSame('Renamed Service', $service->getName());
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/service-admin/1/delete');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(2);

        $response = $this->runApp('DELETE', '/api/user/service-admin/1/delete');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->loginUser(1);

        $response = $this->runApp('DELETE', '/api/user/service-admin/'.($this->serviceId+99).'/delete');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->loginUser(1);

        $response = $this->runApp('DELETE', "/api/user/service-admin/$this->serviceId/delete");

        $this->assertEquals(204, $response->getStatusCode());

        $service = $this->repository->find($this->serviceId);
        $this->assertNull($service);
    }

    public function testSaveConfiguration403()
    {
        $response = $this->runApp('PUT', '/api/user/service-admin/1/save-configuration');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(2);

        $response = $this->runApp('PUT', '/api/user/service-admin/1/save-configuration');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSaveConfiguration404()
    {
        $this->loginUser(1);

        $response = $this->runApp('PUT', '/api/user/service-admin/'.($this->serviceId+99).'/save-configuration');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSaveConfiguration204()
    {
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service-admin/$this->serviceId/save-configuration",
            ['configuration' => \json_encode(['phpClass' => ServiceAdminControllerTest_TestService::class])],
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            [LoggerInterface::class => $this->log],
        );

        $this->assertEquals(204, $response->getStatusCode());

        $service = $this->repository->find($this->serviceId);
        $this->assertSame(ServiceAdminControllerTest_TestService::class, $service->getConfiguration()->phpClass);
        $this->assertSame(['called onConfigurationChange'], $this->log->getMessages());
    }

    public function testSaveConfiguration400()
    {
        $this->loginUser(1);

        $response1 = $this->runApp(
            'PUT',
            "/api/user/service-admin/$this->serviceId/save-configuration",
            ['configuration' => ['invalid']],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
        $response2 = $this->runApp(
            'PUT',
            "/api/user/service-admin/$this->serviceId/save-configuration",
            ['configuration' => "invalid"],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertEquals(400, $response1->getStatusCode());
        $this->assertEquals(400, $response2->getStatusCode());
    }

    private function setupDb(): void
    {
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $this->helper->addCharacterMain('User', 1, [Role::SERVICE_ADMIN]);
        $this->helper->addCharacterMain('Admin', 2, [Role::USER]);

        $service = (new Service())->setName('S1');

        $em->persist($service);
        $em->flush();

        $this->serviceId = $service->getId();
    }
}
