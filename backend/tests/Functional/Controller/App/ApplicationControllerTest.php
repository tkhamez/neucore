<?php declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Entity\Group;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class ApplicationControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->repoFactory = new RepositoryFactory($this->helper->getEm());
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
        $this->helper->getEm()->persist($group);
        $app = $this->helper->addApp('Test App', 'boring-test-secret', [Role::APP])->addGroup($group);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($app->getId().':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/v1/show', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $app->getId(),
            'name' => 'Test App',
            'groups' => [['id' => $group->getId(), 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]],
            'roles' => ['app'],
        ], $this->parseJsonBody($response));
    }
}
