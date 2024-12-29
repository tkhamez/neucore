<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use Neucore\Entity\Role;
use Neucore\Entity\Group;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class ApplicationControllerTest extends WebTestCase
{
    private Helper $helper;

    protected function setUp(): void
    {
        $this->helper = new Helper();
    }

    public function testShowV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowV1200()
    {
        $this->helper->emptyDb();
        $group = (new Group())->setName('g1');
        $this->helper->getObjectManager()->persist($group);
        $app = $this->helper->addApp('Test App', 'boring-test-secret', [Role::APP])->addGroup($group);
        $this->helper->getObjectManager()->flush();

        $headers = ['Authorization' => 'Bearer ' . base64_encode($app->getId() . ':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/v1/show', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $app->getId(),
            'name' => 'Test App',
            'groups' => [['id' => $group->getId(), 'name' => 'g1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]],
            'roles' => ['app'],
            'eveLogins' => [],
        ], $this->parseJsonBody($response));
    }
}
