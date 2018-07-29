<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\Group;
use Brave\Core\Roles;
use Brave\Core\Service\EsiApi;
use Brave\Core\Service\OAuthToken;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class AllianceControllerTest extends WebTestCase
{
    private $helper;

    private $em;

    private $alliId1;

    private $alliId2;

    private $groupId1;

    private $groupId2;

    private $alliRepo;

    private $alliApi;

    private $esi;

    private $log;

    public function setUp()
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->em = $this->helper->getEm();

        $this->alliRepo = new AllianceRepository($this->em);

        // mock Swagger API
        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());
        $oauth = $this->createMock(GenericProvider::class);
        $ts = new OAuthToken($oauth, $this->em, $this->log);
        $this->alliApi = $this->createMock(AllianceApi::class);
        $corpApi = $this->createMock(CorporationApi::class);
        $charApi = $this->createMock(CharacterApi::class);
        $this->esi = new EsiApi($this->log, $ts, $this->alliApi, $corpApi, $charApi);
    }

    public function testAll403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/alliance/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/alliance/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/alliance/all');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                ['id' => 111, 'name' => 'alli 1', 'ticker' => 'a1'],
                ['id' => 222, 'name' => 'alli 2', 'ticker' => 'a2'],
                ['id' => 333, 'name' => 'alli 3', 'ticker' => 'a3']
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testWithGroups403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/alliance/with-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/alliance/with-groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithGroups200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/alliance/with-groups');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                ['id' => 111, 'name' => 'alli 1', 'ticker' => 'a1', 'groups' => [
                    ['id' => $this->groupId1, 'name' => 'group 1', 'visibility' => Group::VISIBILITY_PRIVATE]
                ]],
                ['id' => 222, 'name' => 'alli 2', 'ticker' => 'a2', 'groups' => [
                    ['id' => $this->groupId1, 'name' => 'group 1', 'visibility' => Group::VISIBILITY_PRIVATE],
                    ['id' => $this->groupId2, 'name' => 'group 2', 'visibility' => Group::VISIBILITY_PRIVATE]
                ]]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('POST', '/api/user/alliance/add/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('POST', '/api/user/alliance/add/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAdd400()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiApi class
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception("failed to coerce value '123456789123' into ...", 400))
        );

        $response = $this->runApp('POST', '/api/user/alliance/add/123456789123', null, null, [
            EsiApi::class => $this->esi
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAdd404()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiApi class
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception("Alliance not found", 404))
        );

        $response = $this->runApp('POST', '/api/user/alliance/add/123', null, null, [
            EsiApi::class => $this->esi
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAdd409()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('POST', '/api/user/alliance/add/111');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testAdd503()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiApi class
        $this->alliApi->method('getAlliancesAllianceId')->will(
            $this->throwException(new \Exception("ESI down.", 503))
        );

        $response = $this->runApp('POST', '/api/user/alliance/add/123', null, null, [
            EsiApi::class => $this->esi
        ]);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testAdd201()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiApi class
        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The Alliance.',
            'ticker' => '-AT-',
        ]));

        $response = $this->runApp('POST', '/api/user/alliance/add/123456', null, null, [
            EsiApi::class => $this->esi
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 123456, 'name' => 'The Alliance.', 'ticker' => '-AT-'],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        // check that aliance was created in db
        $alli = $this->alliRepo->find(123456);

        $this->assertSame(123456, $alli->getId());
        $this->assertSame('The Alliance.', $alli->getName());
        $this->assertSame('-AT-', $alli->getTicker());
    }

    public function testAddGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/alliance/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/alliance/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/add-group/5');
        $response2 = $this->runApp('PUT', '/api/user/alliance/123/add-group/'.$this->groupId2);
        $response3 = $this->runApp('PUT', '/api/user/alliance/123/add-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testAddGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/add-group/'.$this->groupId2);
        $response2 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/add-group/'.$this->groupId2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    public function testRemoveGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/alliance/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/alliance/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/remove-group/5');
        $response2 = $this->runApp('PUT', '/api/user/alliance/123/remove-group/'.$this->groupId1);
        $response3 = $this->runApp('PUT', '/api/user/alliance/123/remove-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testRemoveGroup500()
    {
        $this->setupDb();
        $this->loginUser(7);

        $em = $this->helper->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $res = $this->runApp(
            'PUT',
            '/api/user/alliance/'.$this->alliId1.'/remove-group/'.$this->groupId1,
            null,
            null,
            [
                EntityManagerInterface::class => $em,
                LoggerInterface::class => $this->log
            ]
        );
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/remove-group/'.$this->groupId1);
        $response2 = $this->runApp('PUT', '/api/user/alliance/'.$this->alliId1.'/remove-group/'.$this->groupId1);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    private function setupDb()
    {
        $this->helper->emptyDb();

        $this->helper->addCharacterMain('User', 6, [Roles::USER]);
        $this->helper->addCharacterMain('Admin', 7, [Roles::USER, Roles::GROUP_ADMIN]);

        $alli1 = (new Alliance())->setId(111)->setTicker('a1')->setName('alli 1');
        $alli2 = (new Alliance())->setId(222)->setTicker('a2')->setName('alli 2');
        $alli3 = (new Alliance())->setId(333)->setTicker('a3')->setName('alli 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $alli1->addGroup($group1);
        $alli2->addGroup($group1);
        $alli2->addGroup($group2);

        $this->em->persist($alli1);
        $this->em->persist($alli2);
        $this->em->persist($alli3);
        $this->em->persist($group1);
        $this->em->persist($group2);

        $this->em->flush();

        $this->alliId1 = $alli1->getId();
        $this->alliId2 = $alli2->getId();

        $this->groupId1 = $group1->getId();
        $this->groupId2 = $group2->getId();
    }
}
