<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Character;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Repository\AllianceRepository;
use Neucore\Entity\Corporation;
use Neucore\Repository\CorporationRepository;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
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
    private static WriteErrorListener $writeErrorListener;

    private Helper $h;

    private EntityManagerInterface $em;

    private Group $group1;

    private int $gid2;

    private Player $player7;

    private CorporationRepository $corpRepo;

    private AllianceRepository $alliRepo;

    private Client $client;

    private Logger $log;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
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

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testFind403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/corporation/find/abc');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(6); # not a group-admin
        $response2 = $this->runApp('GET', '/api/user/corporation/find/abc');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testFind200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('GET', '/api/user/corporation/find/rp%20'); // only 2 chars
        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response1));

        $response2 = $this->runApp('GET', '/api/user/corporation/find/rp%202');
        $this->assertSame(200, $response2->getStatusCode());
        $this->assertSame([[
            'id' => 222,
            'name' => '1 corp 2',
            'ticker' => 't200'
        ]], $this->parseJsonBody($response2));

        $response3 = $this->runApp('GET', '/api/user/corporation/find/300');
        $this->assertSame(200, $response3->getStatusCode());
        $this->assertSame([[
            'id' => 333,
            'name' => 'corp 3',
            'ticker' => 't300'
        ]], $this->parseJsonBody($response3));
    }

    public function testCorporations403()
    {
        $this->setupDb();

        $response1 = $this->runApp('POST', '/api/user/corporation/corporations');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(6); # not a group-admin
        $response2 = $this->runApp('POST', '/api/user/corporation/corporations');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testCorporations400()
    {
        $this->setUpDb();
        $this->loginUser(7);

        $response = $this->runApp(
            'POST', '/api/user/corporation/corporations',
            null,
            ['Content-Type' => 'application/json']
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCorporations200()
    {
        $this->setUpDb();
        $this->loginUser(7);

        $response = $this->runApp(
            'POST', '/api/user/corporation/corporations',
            [222, 111],
            ['Content-Type' => 'application/json']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = $this->parseJsonBody($response);
        $expected = [
            ['id' => 222, 'name' => '1 corp 2', 'ticker' => 't200'],
            ['id' => 111, 'name' => '2 corp 1', 'ticker' => 't100']
        ];
        $this->assertSame($expected, $body);
    }

    public function testWithGroups403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a group-admin

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
                ['id' => 222, 'name' => '1 corp 2', 'ticker' => 't200', 'alliance' => null, 'groups' => [
                    ['id' => $this->group1->getId(), 'name' => 'group 1', 'description' => null,
                        'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
                    ['id' => $this->gid2, 'name' => 'group 2', 'description' => null,
                        'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]
                ]],
                ['id' => 111, 'name' => '2 corp 1', 'ticker' => 't100', 'alliance' => null, 'groups' => [
                    ['id' => $this->group1->getId(), 'name' => 'group 1', 'description' => null,
                        'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]
                ]],
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('POST', '/api/user/corporation/add/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a group-admin

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

        $this->loginUser(6); # not a group-admin

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

        $this->loginUser(6); # not a group-admin

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
            '/api/user/corporation/123/remove-group/'.$this->group1->getId()
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

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $res = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->group1->getId(),
            null,
            null,
            [ObjectManager::class => $this->em, LoggerInterface::class => $this->log]
        );
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->group1->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/111/remove-group/'.$this->group1->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    public function testTrackingDirector403()
    {
        $response = $this->runApp('GET', '/api/user/corporation/123/tracking-director');
        $this->assertEquals(403, $response->getStatusCode());

        $this->h->emptyDb();
        $this->h->addCharacterMain('Tracking Admin', 7, [Role::USER, Role::TRACKING]);
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/123/tracking-director');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testTrackingDirector200()
    {
        $this->h->emptyDb();
        $login1 = (new EveLogin())->setName(EveLogin::NAME_TRACKING);
        $login2 = (new EveLogin())->setName('Other');
        $corp = (new Corporation())->setId(123)->setName('Corp 123');
        $player = (new Player())->setName('Player');
        $char1a = (new Character())->setId(1020301)->setName('Dir 1')->setPlayer($player)->setCorporation($corp);
        $char1b = (new Character())->setId(1020302)->setName('Dir 2')->setPlayer($player)->setCorporation($corp);
        $token1a = (new EsiToken())->setEveLogin($login1)->setCharacter($char1a)->setValidToken(true)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $token1b = (new EsiToken())->setEveLogin($login1)->setCharacter($char1b)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $token2 = (new EsiToken())->setEveLogin($login2)->setCharacter($char1a)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(time());
        $this->em->persist($login1);
        $this->em->persist($login2);
        $this->em->persist($corp);
        $this->em->persist($player);
        $this->em->persist($char1a);
        $this->em->persist($char1b);
        $this->em->persist($token1a);
        $this->em->persist($token1b);
        $this->em->persist($token2);
        $this->h->addCharacterMain('Tracking Admin', 8, [Role::USER, Role::TRACKING_ADMIN]);
        $this->em->clear();

        $this->loginUser(8); # tracking-admin

        $response = $this->runApp('GET', '/api/user/corporation/123/tracking-director');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => 1020301, 'name' => 'Dir 1', 'playerId' => $player->getId()]],
            $this->parseJsonBody($response)
        );
    }

    public function testGetGroupsTracking403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/123/get-groups-tracking');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not role tracking-admin

        $response = $this->runApp('GET', '/api/user/corporation/123/get-groups-tracking');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetGroupsTracking404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/corporation/123/get-groups-tracking');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetGroupsTracking200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/corporation/222/get-groups-tracking');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => $this->group1->getId(), 'name' => 'group 1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]],
            $this->parseJsonBody($response)
        );
    }

    public function testAddGroupTracking403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group-tracking/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not a tracking-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group-tracking/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroupTracking404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/corporation/222/add-group-tracking/5');
        $response2 = $this->runApp('PUT', '/api/user/corporation/123/add-group-tracking/'.$this->gid2);
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/add-group-tracking/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testAddGroupTracking204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/222/add-group-tracking/'.$this->gid2
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/222/add-group-tracking/'.$this->gid2
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $corp = $this->corpRepo->find(222);
        $this->assertSame(2, count($corp->getGroupsTracking()));
        $this->assertSame($this->group1->getId(), $corp->getGroupsTracking()[0]->getId());
        $this->assertSame($this->gid2, $corp->getGroupsTracking()[1]->getId());
    }

    public function testRemoveGroupTracking403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group-tracking/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not a tracking-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group-tracking/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroupTracking404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/222/remove-group-tracking/5'
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/123/remove-group-tracking/'.$this->group1->getId()
        );
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/remove-group-tracking/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testRemoveGroupTracking204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/corporation/222/remove-group-tracking/'.$this->group1->getId()
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/corporation/222/remove-group-tracking/'.$this->group1->getId()
        );
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $corp = $this->corpRepo->find(222);
        $this->assertSame([], $corp->getGroupsTracking());
    }

    public function testTrackedCorporations403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not role tracking or tracking-admin

        $response = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testTrackedCorporations200()
    {
        $this->setupDb();
        $this->loginUser(7);

        # tracking role but no group

        $response1 = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response1));

        # add user to group

        $this->player7->addGroup($this->group1);
        $this->em->flush();

        $response2 = $this->runApp('GET', '/api/user/corporation/tracked-corporations');
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertSame([[
            'id' => 222,
            'name' => '1 corp 2',
            'ticker' => 't200',
            'alliance' => null,
            'trackingLastUpdate' => '2019-12-19T13:44:02Z',
        ]], $this->parseJsonBody($response2));
    }

    public function testAllTrackedCorporations403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/all-tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); # not tracking-admin

        $response = $this->runApp('GET', '/api/user/corporation/all-tracked-corporations');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAllTrackedCorporations200()
    {
        $this->setupDb();
        $this->loginUser(8); # tracking-admin

        $response3 = $this->runApp('GET', '/api/user/corporation/all-tracked-corporations');
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertSame([[
            'id' => 222,
            'name' => '1 corp 2',
            'ticker' => 't200',
            'alliance' => null,
            'trackingLastUpdate' => '2019-12-19T13:44:02Z',
        ]], $this->parseJsonBody($response3));
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
        $params = '?inactive=7&active=12&account=true&valid-token=false&token-status-changed=1&mail-count=1';

        # role tracking but missing group

        $this->loginUser(7);

        $response1 = $this->runApp('GET', '/api/user/corporation/222/members' . $params);
        $this->assertEquals(403, $response1->getStatusCode());

        # add user to group

        $this->player7->addGroup($this->group1);
        $this->em->flush();

        $response2 = $this->runApp('GET', '/api/user/corporation/222/members' . $params);
        $this->assertEquals(200, $response2->getStatusCode());
        $result = $this->parseJsonBody($response2);
        $this->assertSame(1, count($result));
        $this->assertSame(6, $result[0]['id']);
        $this->assertSame('m1', $result[0]['name']);
        $this->assertSame(null, $result[0]['location']);
        $this->assertSame(null, $result[0]['logoffDate']);
        $this->assertStringStartsWith((new \DateTime('now -10 days'))->format('Y-m-d'), $result[0]['logonDate']);
        $this->assertSame(null, $result[0]['shipType']);
        $this->assertSame(null, $result[0]['startDate']);
    }

    private function setupDb(): void
    {
        $this->h->emptyDb();

        $char = $this->h->addCharacterMain('User', 6, [Role::USER]);
        $char->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false)
            ->setValidTokenTime(new \DateTime('-1 day -1 minute'));
        $this->player7 = $this->h
            ->addCharacterMain('Group Admin', 7, [Role::USER, Role::GROUP_ADMIN, Role::TRACKING])
            ->getPlayer();
        $this->h->addCharacterMain('User Admin', 8, [Role::USER, Role::TRACKING_ADMIN]);

        $corp1 = (new Corporation())->setId(111)->setTicker('t100')->setName('2 corp 1');
        $corp2 = (new Corporation())->setId(222)->setTicker('t200')->setName('1 corp 2')
            ->setTrackingLastUpdate(new \DateTime('2019-12-19 13:44:02'));
        $corp3 = (new Corporation())->setId(333)->setTicker('t300')->setName('corp 3');

        $this->group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $member = (new CorporationMember())->setId($char->getId())->setName('m1')->setCorporation($corp2)
            ->setLogonDate(new \DateTime('now -10 days'))->setMissingCharacterMailSentNumber(2);

        $corp1->addGroup($this->group1);
        $corp2->addGroup($this->group1);
        $corp2->addGroup($group2);
        $corp2->addGroupTracking($this->group1);

        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->persist($this->group1);
        $this->em->persist($group2);
        $this->em->persist($member);

        $this->em->flush();

        $this->gid2 = $group2->getId();
    }
}
