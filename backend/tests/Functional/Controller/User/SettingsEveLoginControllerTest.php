<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\EveLoginRepository;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class SettingsEveLoginControllerTest extends WebTestCase
{
    private Helper $helper;

    private EveLoginRepository $eveLoginRepo;

    private int $defaultLoginId;

    private int $custom1LoginId;

    private int $playerId;

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
        $this->setupDb();

        $response1 = $this->runApp('POST', '/api/user/settings/eve-login/custom2');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);
        $response2 = $this->runApp('POST', '/api/user/settings/eve-login/custom2');
        $this->assertEquals(403, $response2->getStatusCode());
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
        $this->setupDb();

        $response1 = $this->runApp('DELETE', '/api/user/settings/eve-login/custom1');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);
        $response2 = $this->runApp('DELETE', '/api/user/settings/eve-login/custom1');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('DELETE', '/api/user/settings/eve-login/custom2');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testTokens200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/settings/eve-login/$this->custom1LoginId/tokens");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'eveLoginId' => $this->custom1LoginId,
            'characterId' => 1,
            'playerId' => $this->playerId,
            'validToken' => null,
            'validTokenTime' => null,
            'hasRoles' => null,
            'lastChecked' => null,
            'playerName' => 'Admin',
            'character' => [
                'id' => 1,
                'name' => 'Admin',
                'corporation' => [
                    'id' => 100000,
                    'name' => 'Corp',
                    'ticker' => 'C',
                    'alliance' => [
                        'id' => 100,
                        'name' => 'Alliance',
                        'ticker' => 'A',
                    ],
                ],
            ],
        ]], $this->parseJsonBody($response));
    }

    public function testTokens403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', "/api/user/settings/eve-login/$this->custom1LoginId/tokens");
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);
        $response2 = $this->runApp('GET', "/api/user/settings/eve-login/$this->custom1LoginId/tokens");
        $this->assertEquals(403, $response2->getStatusCode());

        $this->loginUser(1);
        $response2 = $this->runApp('GET', "/api/user/settings/eve-login/$this->defaultLoginId/tokens");
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testTokens404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/settings/eve-login/'.($this->custom1LoginId + 10).'/tokens');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testList403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/settings/eve-login/list');
        $this->assertEquals(403, $response1->getStatusCode());
    }

    public function testList200()
    {
        $this->setupDb();
        $this->loginUser(2);

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
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/settings/eve-login');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);
        $response2 = $this->runApp('PUT', '/api/user/settings/eve-login');
        $this->assertEquals(403, $response2->getStatusCode());
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
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/settings/eve-login/roles');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(2);
        $response2 = $this->runApp('GET', '/api/user/settings/eve-login/roles');
        $this->assertEquals(403, $response2->getStatusCode());
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
        $customLogin = (new EveLogin())
            ->setName('custom1')
            ->setDescription('A text.')
            ->setEsiScopes('scope1 scope2')
            ->setEveRoles(['Role1', 'Role2']);
        $this->helper->getEm()->persist($customLogin);

        $alliance = (new Alliance())->setId(100)->setName('Alliance')->setTicker('A');
        $corporation = (new Corporation())->setId(100000)->setName('Corp')->setTicker('C')->setAlliance($alliance);
        $this->helper->getEm()->persist($alliance);
        $this->helper->getEm()->persist($corporation);

        $admin = $this->helper->addCharacterMain('Admin', 1, [Role::USER, Role::SETTINGS]);
        $admin->setCorporation($corporation);

        $customEsiToken = (new EsiToken())->setEveLogin($customLogin)->setCharacter($admin)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time() + 1000);
        $this->helper->getEm()->persist($customEsiToken);

        $this->helper->addCharacterMain('User', 2, [Role::USER]);

        $this->helper->getEm()->clear();

        $this->defaultLoginId = $admin->getEsiToken(EveLogin::NAME_DEFAULT)->getEveLogin()->getId();
        $this->custom1LoginId = $customLogin->getId();
        $this->playerId = $admin->getPlayer()->getId();
    }
}
