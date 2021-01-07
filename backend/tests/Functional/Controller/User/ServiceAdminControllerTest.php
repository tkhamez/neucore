<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\ServiceRepository;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class ServiceAdminControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var ServiceRepository
     */
    private $repository;

    /**
     * @var int
     */
    private $serviceId;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->repository = RepositoryFactory::getInstance($this->helper->getObjectManager())->getServiceRepository();

        $_SESSION = null;
        $this->setupDb();
    }

    public function testList403()
    {
        $response = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(2);

        $response = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testList200()
    {
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service-admin/list');
        $this->assertEquals(200, $response->getStatusCode());
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
            "/api/user/service-admin/{$this->serviceId}/rename",
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
            "/api/user/service-admin/{$this->serviceId}/rename",
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

        $response = $this->runApp('DELETE', "/api/user/service-admin/{$this->serviceId}/delete");

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
            "/api/user/service-admin/{$this->serviceId}/save-configuration",
            ['configuration' => \json_encode(['a' => '1'])],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $this->assertEquals(204, $response->getStatusCode());

        $service = $this->repository->find($this->serviceId);
        $this->assertSame(['a' => '1'], \json_decode((string)$service->getConfiguration(), true));
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
