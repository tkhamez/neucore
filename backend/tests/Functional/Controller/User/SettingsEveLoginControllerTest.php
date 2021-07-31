<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\EveLoginRepository;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class SettingsEveLoginControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EveLoginRepository
     */
    private $eveLoginRepo;

    /**
     * @var int
     */
    private $defaultLoginId;

    protected function setUp(): void
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->helper->emptyDb();

        $em = $this->helper->getEm();
        $this->eveLoginRepo = (new RepositoryFactory($em))->getEveLoginRepository();
    }

    public function testCreate201()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/settings/eve-login/custom2');

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame([
            'id' => $this->defaultLoginId + 1,
            'name' => 'custom2',
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => [],
        ], $this->parseJsonBody($response));

        $login = $this->eveLoginRepo->find($this->defaultLoginId + 1);
        $this->assertSame('custom2', $login->getName());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/settings/eve-login/custom,2');
        $this->assertEquals(400, $response->getStatusCode());

        $response = $this->runApp('POST', '/api/user/settings/eve-login/'.EveLogin::INTERNAL_LOGIN_PREFIX.'test');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/settings/eve-login/custom2');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate409()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/settings/eve-login/custom1');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('DELETE', '/api/user/settings/eve-login/'.($this->defaultLoginId - 1));
        $this->assertEquals(204, $response->getStatusCode());

        $login = $this->eveLoginRepo->find('custom1');
        $this->assertNull($login);
    }

    public function testDelete400()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('DELETE', '/api/user/settings/eve-login/'.$this->defaultLoginId);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/settings/eve-login/custom1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('DELETE', '/api/user/settings/eve-login/custom2');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testList403()
    {
        $response = $this->runApp('GET', '/api/user/settings/eve-login/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testList200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/settings/eve-login/list');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'id' => $this->defaultLoginId,
            'name' => EveLogin::NAME_DEFAULT,
            'description' => '',
            'esiScopes' => '',
            'eveRoles' => [],
        ], [
            'id' => $this->defaultLoginId - 1,
            'name' => 'custom1',
            'description' => 'A text.',
            'esiScopes' => 'scope1 scope2',
            'eveRoles' => ['Role1', 'Role2'],
        ]], $this->parseJsonBody($response));
    }

    public function testUpdate200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/eve-login',
            [
                'id' => $this->defaultLoginId - 1,
                'name' => 'custom1a',
                'description' => 'An updated text.',
                'esiScopes' => 'scope3',
                'eveRoles' => ['Role3'],
            ],
            ['Content-Type' => 'application/json']
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            'id' => $this->defaultLoginId - 1,
            'name' => 'custom1a',
            'description' => 'An updated text.',
            'esiScopes' => 'scope3',
            'eveRoles' => ['Role3'],
        ], $this->parseJsonBody($response));

        $login = $this->eveLoginRepo->find($this->defaultLoginId - 1);
        $this->assertSame('custom1a', $login->getName());
        $this->assertSame('An updated text.', $login->getDescription());
        $this->assertSame('scope3', $login->getEsiScopes());
        $this->assertSame(['Role3'], $login->getEveRoles());
    }

    public function testUpdate400_invalidBody()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/eve-login',
            ['id' => 'custom1'],
            ['Content-Type' => 'application/json']
        );
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdate400_invalidName()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/eve-login',
            [
                'id' => $this->defaultLoginId - 1,
                'name' => 'custom1 a',
                'description' => 'An updated text.',
                'esiScopes' => 'scope3',
                'eveRoles' => ['Role3'],
            ],
            ['Content-Type' => 'application/json']
        );
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdate403()
    {
        $response = $this->runApp('PUT', '/api/user/settings/eve-login');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdate404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            '/api/user/settings/eve-login',
            (new EveLogin())->setId($this->defaultLoginId + 1)->setName('custom2'),
            ['Content-Type' => 'application/json']
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRoles403()
    {
        $response = $this->runApp('GET', '/api/user/settings/eve-login/roles');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRoles200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/settings/eve-login/roles');
        $this->assertEquals(200, $response->getStatusCode());
        $body =  $this->parseJsonBody($response);
        $this->assertIsArray($body);
        $this->assertIsString($body[0]);
    }

    private function setupDb(): void
    {
        $login = (new EveLogin())
            ->setName('custom1')
            ->setDescription('A text.')
            ->setEsiScopes('scope1 scope2')
            ->setEveRoles(['Role1', 'Role2']);
        $this->helper->getEm()->persist($login);

        $char = $this->helper->addCharacterMain('Admin', 1, [Role::USER, Role::SETTINGS]);
        $this->defaultLoginId = $char->getEsiToken(EveLogin::NAME_DEFAULT)->getEveLogin()->getId();
    }
}
