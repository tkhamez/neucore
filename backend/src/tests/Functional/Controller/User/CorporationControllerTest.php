<?php declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\CorporationMember;
use Neucore\Entity\Role;
use Neucore\Repository\AllianceRepository;
use Neucore\Entity\Corporation;
use Neucore\Repository\CorporationRepository;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Client;
use Tests\WriteErrorListener;

class CorporationControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $h;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    private $gid1;

    private $gid2;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var AllianceRepository
     */
    private $alliRepo;

    /**
     * @var Client
     */
    private $client;

    private $log;

    public function setUp()
    {
        $_SESSION = null;

        $this->h = new Helper();
        $this->em = $this->h->getEm();

        $repositoryFactory = new RepositoryFactory($this->em);
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());
        $this->client = new Client();
    }

    public function testAll403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                ['id' => 111, 'name' => 'corp 1', 'ticker' => 't1', 'alliance' => null],
                ['id' => 222, 'name' => 'corp 2', 'ticker' => 't2', 'alliance' => null],
                ['id' => 333, 'name' => 'corp 3', 'ticker' => 't3', 'alliance' => null]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testWithGroups403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithGroups200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                ['id' => 111, 'name' => 'corp 1', 'ticker' => 't1', 'alliance' => null, 'groups' => [
                    ['id' => $this->gid1, 'name' => 'group 1', 'visibility' => Group::VISIBILITY_PRIVATE]
                ]],
                ['id' => 222, 'name' => 'corp 2', 'ticker' => 't2', 'alliance' => null, 'groups' => [
                    ['id' => $this->gid1, 'name' => 'group 1', 'visibility' => Group::VISIBILITY_PRIVATE],
                    ['id' => $this->gid2, 'name' => 'group 2', 'visibility' => Group::VISIBILITY_PRIVATE]
                ]]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('POST', '/api/user/corporation/add/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('POST', '/api/user/corporation/add/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAdd400()
    {
        $this->setupDb();
        $this->loginUser(7);

        $this->client->setResponse(new Response(400));

        $response = $this->runApp(
            'POST',
            '/api/user/corporation/add/123456789123',
            null,
            null,
            [
                ClientInterface::class => $this->client,
                LoggerInterface::class => $this->log
            ]
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAdd404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $this->client->setResponse(new Response(404));

        $response = $this->runApp(
            'POST',
            '/api/user/corporation/add/123',
            null,
            null,
            [
                ClientInterface::class => $this->client,
                LoggerInterface::class => $this->log
            ]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAdd409()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('POST', '/api/user/corporation/add/111');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testAdd503()
    {
        $this->setupDb();
        $this->loginUser(7);

        $this->client->setResponse(new Response(503));

        $response = $this->runApp(
            'POST',
            '/api/user/corporation/add/123',
            null,
            null,
            [
                ClientInterface::class => $this->client,
                LoggerInterface::class => $this->log
            ]
        );

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testAdd201WithoutAlliance()
    {
        $this->setupDb();
        $this->loginUser(7);

        $this->client->setResponse(new Response(200, [], '{
            "name": "The Corp.",
            "ticker": "-CT-",
            "alliance_id": null
        }'));

        $response = $this->runApp(
            'POST',
            '/api/user/corporation/add/456123',
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 456123, 'name' => 'The Corp.', 'ticker' => '-CT-', 'alliance' => null],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        // check db
        $corp = $this->corpRepo->find(456123);
        $this->assertNull($corp->getAlliance());
        $this->assertSame(456123, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-CT-', $corp->getTicker());
    }

    public function testAdd201WitAlliance()
    {
        $this->setupDb();
        $this->loginUser(7);

        $this->client->setResponse(
            new Response(200, [], '{
                "name": "The Corp.",
                "ticker": "-CT-",
                "alliance_id": "123456"
            }'),
            new Response(200, [], '{
                "name": "The Alliance.",
                "ticker": "-AT-"
            }')
        );

        $response = $this->runApp(
            'POST',
            '/api/user/corporation/add/456123',
            null,
            null,
            [ClientInterface::class => $this->client]
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 456123, 'name' => 'The Corp.', 'ticker' => '-CT-', 'alliance' => [
                'id' => 123456, 'name' => 'The Alliance.', 'ticker' => '-AT-'
            ]],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        // check that corp and alliance were created in db
        $corp = $this->corpRepo->find(456123);
        $alli = $this->alliRepo->find(123456);

        $this->assertSame($alli->getId(), $corp->getAlliance()->getId());

        $this->assertSame(456123, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-CT-', $corp->getTicker());

        $this->assertSame(123456, $alli->getId());
        $this->assertSame('The Alliance.', $alli->getName());
        $this->assertSame('-AT-', $alli->getTicker());
    }

    public function testAddGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/corporation/111/add-group/5');
        $response2 = $this->runApp('PUT', '/api/user/corporation/123/add-group/'.$this->gid2);
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testAddGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/add-group/'.$this->gid2
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/add-group/'.$this->gid2
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    public function testRemoveGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/5'
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/123/remove-group/'.$this->gid1
        );
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testRemoveGroup500()
    {
        $this->setupDb();
        $this->loginUser(7);

        $em = $this->h->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $res = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->gid1,
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

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->gid1
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->gid1
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    public function testTrackedCorporations403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role tracking

        $response = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testTrackedCorporations200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'id' => 222,
            'name' => 'corp 2',
            'ticker' => 't2',
            'alliance' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testMembers403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/222/members');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role tracking

        $response = $this->runApp('GET', '/api/user/corporation/222/members');
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testMembers200()
    {
        $this->setupDb();

        $this->loginUser(7);

        $params = '?inactive=7&active=12';
        $response = $this->runApp('GET', '/api/user/corporation/222/members' . $params);
        $this->assertEquals(200, $response->getStatusCode());
        $result = $this->parseJsonBody($response);
        $this->assertSame(1, count($result));
        $this->assertSame(101, $result[0]['id']);
        $this->assertSame('m1', $result[0]['name']);
        $this->assertSame(null, $result[0]['locationId']);
        $this->assertSame(null, $result[0]['logoffDate']);
        $this->assertStringStartsWith((new \DateTime('now -10 days'))->format('Y-m-d'), $result[0]['logonDate']);
        $this->assertSame(null, $result[0]['shipTypeId']);
        $this->assertSame(null, $result[0]['startDate']);
    }

    private function setupDb()
    {
        $this->h->emptyDb();

        $this->h->addCharacterMain('User', 6, [Role::USER]);
        $this->h->addCharacterMain('Admin', 7, [Role::USER, Role::GROUP_ADMIN, Role::TRACKING]);

        $corp1 = (new Corporation())->setId(111)->setTicker('t1')->setName('corp 1');
        $corp2 = (new Corporation())->setId(222)->setTicker('t2')->setName('corp 2');
        $corp3 = (new Corporation())->setId(333)->setTicker('t3')->setName('corp 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $member = (new CorporationMember())->setId(101)->setName('m1')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -10 days'));

        $corp1->addGroup($group1);
        $corp2->addGroup($group1);
        $corp2->addGroup($group2);

        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($member);

        $this->em->flush();

        $this->gid1 = $group1->getId();
        $this->gid2 = $group2->getId();
    }
}
