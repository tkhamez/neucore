<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Controller\User\ServiceController;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Plugin\ServiceAccountData;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;

class ServiceControllerTest extends WebTestCase
{
    private const ERROR_NO_SERVICE_OBJECT =
        'ServiceController: The configured service class does not exist of does not implement '.
        'Neucore\Plugin\ServiceInterface.';

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Player
     */
    private $player;

    // entity IDs
    private $g1;
    private $s1;
    private $s2;
    private $s3;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->em = $this->helper->getEm();
        $this->log = new Logger('Test');
    }

    public function testGet403()
    {
        $response = $this->runApp('GET', '/api/user/service/1/get');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet403_MissingGroup()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/{$this->s3}/get");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service/'.($this->s1 + 100).'/get');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGet200Admin()
    {
        $this->setupDb();
        $this->loginUser(4);

        $response = $this->runApp('GET', "/api/user/service/{$this->s3}/get");
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGet200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/{$this->s1}/get");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s1,
                'name' => 'S1',
                'configuration' => json_encode([
                    'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService',
                    'requiredGroups' => $this->g1,
                ])
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAccounts403()
    {
        $response = $this->runApp('GET', "/api/user/service/1/accounts");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAccounts403_MissingGroup()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/{$this->s3}/accounts");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAccounts404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service/'.($this->s1 + 100).'/accounts');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAccounts200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/{$this->s1}/accounts");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['characterId' => 1, 'username' => 'u', 'password' => 'p', 'email' => 'e',
                'status' => ServiceAccountData::STATUS_ACTIVE],
            ['characterId' => 3, 'username' => null, 'password' => null, 'email' => null, 'status' => null],
        ], $this->parseJsonBody($response));
    }

    public function testAccounts500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/{$this->s2}/accounts", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testAccounts500()
    {
        $this->setupDb();
        $this->loginUser(1);

        // and add a group, so that ServiceControllerTest_TestService can throw an exception
        $group4 = (new Group())->setName('G4');
        $this->player->addGroup($group4);
        $this->em->persist($group4);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('GET', "/api/user/service/{$this->s1}/accounts");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRegister403()
    {
        $response = $this->runApp('POST', "/api/user/service/1/register");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRegister403_MissingGroup()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/{$this->s3}/register");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRegister404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', '/api/user/service/'.($this->s1+99).'/register');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRegister200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            'characterId' => 2,
            'username' => 'u2',
            'password' => 'p2',
            'email' => 'e2',
            'status' => ServiceAccountData::STATUS_ACTIVE,
        ], $this->parseJsonBody($response));
    }

    public function testRegister409_noMain()
    {
        $this->setupDb();
        $this->loginUser(1);

        $this->player->getCharacters()[0]->setMain(false);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('no_main', $response->getReasonPhrase());
    }

    public function testRegister409_AlreadyRegistered()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('already_registered', $response->getReasonPhrase());
    }

    public function testRegister409_RegisterFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        // and add a group, so that ServiceControllerTest_TestService will throw an exception
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $group5 = (new Group())->setName('G5');
        $this->player->addGroup($group5);
        $this->em->persist($group5);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('test', $response->getReasonPhrase());
    }

    public function testRegister500_RegisterFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        // and add a group, so that ServiceControllerTest_TestService will throw an exception
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $group3 = (new Group())->setName('G3');
        $this->player->addGroup($group3);
        $this->em->persist($group3);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRegister500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/{$this->s2}/register", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testRegister500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        // and add a group, so that ServiceControllerTest_TestService throws an exception
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $group4 = (new Group())->setName('G4');
        $this->player->addGroup($group4);
        $this->em->persist($group4);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/{$this->s1}/register");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpdateAccount403_NotLoggedIn()
    {
        $response = $this->runApp('PUT', "/api/user/service/1/update-account/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateAccount403_MissingGroup()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s3}/update-account/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateAccount404_NoService()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', '/api/user/service/'.($this->s1+99).'/update-account/1');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount404_NoCharacter()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/update-account/7");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/update-account/2");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount204()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/update-account/1");
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testUpdateAccount500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s2}/update-account/1", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testUpdateAccount500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // and add a group, so that ServiceControllerTest_TestService throws an exception
        $group4 = (new Group())->setName('G4');
        $this->player->addGroup($group4);
        $this->em->persist($group4);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/update-account/1");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpdateAccount500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/update-account/3");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testResetPassword403_NotLoggedIn()
    {
        $response = $this->runApp('PUT', "/api/user/service/1/reset-password/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testResetPassword403_MissingGroup()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s3}/reset-password/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testResetPassword404_NoService()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', '/api/user/service/'.($this->s1+99).'/reset-password/1');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword404_NoCharacter()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/reset-password/7");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/reset-password/2");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/reset-password/1");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('new-pass', $this->parseJsonBody($response));
    }

    public function testResetPassword500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s2}/reset-password/1", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testResetPassword500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // and add a group, so that ServiceControllerTest_TestService throws an exception
        $group4 = (new Group())->setName('G4');
        $this->player->addGroup($group4);
        $this->em->persist($group4);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/reset-password/1");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testResetPassword500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/{$this->s1}/reset-password/3");
        $this->assertEquals(500, $response->getStatusCode());
    }

    private function setupDb(): void
    {
        $group1 = (new Group())->setName('G1');
        $group2 = (new Group())->setName('G2');
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->flush();

        $service1 = (new Service())->setName('S1')->setConfiguration((string)json_encode([
            'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService',
            'requiredGroups' => $group1->getId(),
        ]));
        $service2 = (new Service())->setName('S2')->setConfiguration((string)json_encode([
            'phpClass' => ServiceController::class
        ]));
        $service3 = (new Service())->setName('S3')->setConfiguration((string)json_encode([
            'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService',
            'requiredGroups' => implode(',', [$group1->getId(), $group2->getId()]),
        ]));
        $this->em->persist($service1);
        $this->em->persist($service2);
        $this->em->persist($service3);
        $this->em->flush();

        $this->player = $this->helper->addCharacterMain('Char1', 1, [Role::USER], [$group1->getName()])
            ->getPlayer();
        $this->helper->addCharacterToPlayer('Char2', 2, $this->player);
        $this->helper->addCharacterToPlayer('Char3', 3, $this->player);
        $this->helper->addCharacterMain('Admin', 4, [Role::USER, ROLE::SERVICE_ADMIN]);

        $this->g1 = $group1->getId();
        $this->s1 = $service1->getId();
        $this->s2 = $service2->getId();
        $this->s3 = $service3->getId();
    }
}
