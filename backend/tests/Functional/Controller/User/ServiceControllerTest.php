<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Controller\User\ServiceController;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
use Neucore\Entity\SystemVariable;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\ServiceAccountData;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;

class ServiceControllerTest extends WebTestCase
{
    private const ERROR_NO_SERVICE_OBJECT =
        'ServiceController: The configured service class does not exist or does not implement '.
        'Neucore\Plugin\ServiceInterface.';

    private Helper $helper;

    private EntityManagerInterface $em;

    private Logger $log;

    private Player $player;

    private Service $service1;

    // entity IDs
    private int $g1;
    private int $g2;
    private int $g7;
    private int $s1;
    private int $s2;
    private int $s3;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->em = $this->helper->getEm();
        $this->log = new Logger('Test');
    }

    protected function tearDown(): void
    {
        ServiceControllerTest_TestService::$throw = false;
    }

    public function testGet403()
    {
        $response = $this->runApp('GET', '/api/user/service/1/get');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet403_MissingGroup()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(4);

        $response = $this->runApp('GET', "/api/user/service/$this->s3/get");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(4);

        $response1 = $this->runApp('GET', "/api/user/service/$this->s1/get");
        $this->assertEquals(403, $response1->getStatusCode());
    }

    public function testGet404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service/'.($this->s1 + 100).'/get');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGet200_DeactivatedGroups_Admin()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(4);

        $response2 = $this->runApp('GET', "/api/user/service/$this->s1/get?allowAdmin=true");
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testGet200_MissingGroups_Admin()
    {
        $this->setupDb();
        $this->loginUser(4);

        $response = $this->runApp('GET', "/api/user/service/$this->s3/get?allowAdmin=true");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s3,
                'name' => 'S3',
                'configuration' => [
                    'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService',
                    'psr4Prefix' => '',
                    'psr4Path' => '',
                    'oneAccount' => false,
                    'requiredGroups' => [$this->g2, $this->g7],
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [ServiceConfiguration::ACTION_UPDATE_ACCOUNT],
                    'URLs' => [],
                    'textAccount' => '',
                    'textTop' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testGet200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/$this->s1/get");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s1,
                'name' => 'S1',
                'configuration' => [
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [ServiceConfiguration::ACTION_RESET_PASSWORD],
                    'URLs' => [],
                    'textAccount' => '',
                    'textTop' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ]
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

        $response = $this->runApp('GET', "/api/user/service/$this->s3/accounts");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAccounts403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/$this->s1/accounts");
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

        $response = $this->runApp('GET', "/api/user/service/$this->s1/accounts");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['characterId' => 1, 'username' => 'u', 'password' => 'p', 'email' => 'e',
                'status' => ServiceAccountData::STATUS_ACTIVE, 'name' => 'dn'],
            ['characterId' => 3, 'username' => null, 'password' => null, 'email' => null,
                'status' => null, 'name' => null],
        ], $this->parseJsonBody($response));
    }

    public function testAccounts200_DeactivatedGroups()
    {
        $this->setupDb(true);
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/$this->s1/accounts");
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAccounts500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', "/api/user/service/$this->s2/accounts", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testAccounts500()
    {
        $this->setupDb();
        $this->loginUser(1);

        ServiceControllerTest_TestService::$throw = true;

        $response = $this->runApp('GET', "/api/user/service/$this->s1/accounts");
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

        $response = $this->runApp('POST', "/api/user/service/$this->s3/register");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRegister403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
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

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            'characterId' => 2,
            'username' => 'u2',
            'password' => 'p2',
            'email' => 'e2',
            'status' => ServiceAccountData::STATUS_ACTIVE,
            'name' => 'dn',
        ], $this->parseJsonBody($response));
        $this->assertEquals([new CoreGroup($this->g1, 'G1')], ServiceControllerTest_TestService::$lastGroups);
    }

    public function testRegister200_DeactivatedGroups()
    {
        $this->setupDb(true);
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], ServiceControllerTest_TestService::$lastGroups);
    }

    public function testRegister409_noMain()
    {
        $this->setupDb();
        $this->loginUser(1);

        $this->player->getCharacters()[0]->setMain(false);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"no_main"', $response->getBody()->__toString());
    }

    public function testRegister409_AlreadyRegistered()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"already_registered"', $response->getBody()->__toString());
    }

    public function testRegister409_SecondAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        // switch main, so that a new account can be registered
        // and change service configuration to "oneAccount"
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $configuration = $this->service1->getConfiguration();
        $configuration->oneAccount = true;
        $this->service1->setConfiguration($configuration);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"second_account"', $response->getBody()->__toString());
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

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"test"', $response->getBody()->__toString());
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

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRegister500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('POST', "/api/user/service/$this->s2/register", null, null, [
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
        ServiceControllerTest_TestService::$throw = true;
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp('POST', "/api/user/service/$this->s1/register");
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

        $response = $this->runApp('PUT', "/api/user/service/$this->s3/update-account/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateAccount403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/1");
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

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/7");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/2");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount204()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/1");
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals([new CoreGroup($this->g1, 'G1')], ServiceControllerTest_TestService::$lastGroups);
    }

    public function testUpdateAccount204_DeactivatedGroups()
    {
        $this->setupDb(true);
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/1");
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals([], ServiceControllerTest_TestService::$lastGroups);
    }

    public function testUpdateAllAccounts_403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/service/update-all-accounts/'.$this->player->getId());
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(1);
        $response2 = $this->runApp('PUT', '/api/user/service/update-all-accounts/'.$this->player->getId());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testUpdateAllAccounts_404()
    {
        $this->setupDb();
        $this->loginUser(4);

        $response = $this->runApp('PUT', '/api/user/service/update-all-accounts/'.($this->player->getId() + 10));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAllAccounts_200()
    {
        $this->setupDb();
        $this->loginUser(4);

        $response = $this->runApp('PUT', '/api/user/service/update-all-accounts/'.$this->player->getId(), null, null, [
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([['serviceName' => 'S3', 'characterId' => 1]], $this->parseJsonBody($response));
        $this->assertSame([
            'ServiceController::updateAllAccounts: S3: ',
        ], $this->log->getMessages());
    }

    public function testUpdateAccount500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s2/update-account/1", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testUpdateAccount500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        ServiceControllerTest_TestService::$throw = true;

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/1");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpdateAccount500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/update-account/3");
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

        $response = $this->runApp('PUT', "/api/user/service/$this->s3/reset-password/1");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testResetPassword403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/1");
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

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/7");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/2");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/1");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('new-pass', $this->parseJsonBody($response));
    }

    public function testResetPassword500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s2/reset-password/1", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testResetPassword500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        ServiceControllerTest_TestService::$throw = true;

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/1");
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testResetPassword500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('PUT', "/api/user/service/$this->s1/reset-password/3");
        $this->assertEquals(500, $response->getStatusCode());
    }

    private function setupDb(bool $noRequiredGroupsForService1 = false): void
    {
        $group1 = (new Group())->setName('G1');
        $group2 = (new Group())->setName('G2');
        $group7 = (new Group())->setName('G7');
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group7);
        $this->em->flush();

        $conf1 = new ServiceConfiguration();
        $conf1->actions = [ServiceConfiguration::ACTION_RESET_PASSWORD];
        $conf1->phpClass = 'Tests\Functional\Controller\User\ServiceControllerTest_TestService';
        if (!$noRequiredGroupsForService1) {
            $conf1->requiredGroups = [$group1->getId()];
        }
        $this->service1 = (new Service())->setName('S1')->setConfiguration($conf1);

        $conf2 = new ServiceConfiguration();
        $conf2->phpClass = ServiceController::class;
        $service2 = (new Service())->setName('S2')->setConfiguration($conf2);

        $conf3 = new ServiceConfiguration();
        $conf3->actions = [ServiceConfiguration::ACTION_UPDATE_ACCOUNT];
        $conf3->phpClass = 'Tests\Functional\Controller\User\ServiceControllerTest_TestService';
        $conf3->requiredGroups = [$group2->getId(), $group7->getId()];
        $service3 = (new Service())->setName('S3')->setConfiguration($conf3);

        $this->em->persist($this->service1);
        $this->em->persist($service2);
        $this->em->persist($service3);
        $this->em->flush();

        $this->player = $this->helper->addCharacterMain('Char1', 1, [Role::USER], [$group1->getName()])
            ->getPlayer();
        $this->helper->addCharacterToPlayer('Char2', 2, $this->player);
        $this->helper->addCharacterToPlayer('Char3', 3, $this->player);
        $this->helper->addCharacterMain('Admin', 4, [Role::USER, ROLE::SERVICE_ADMIN, Role::USER_ADMIN]);

        $this->g1 = $group1->getId();
        $this->g2 = $group2->getId();
        $this->g7 = $group7->getId();
        $this->s1 = $this->service1->getId();
        $this->s2 = $service2->getId();
        $this->s3 = $service3->getId();
    }

    private function setupDeactivateAccount(): void
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $corporation = (new Corporation())->setId(101);
        $this->player->getCharacters()[0]->setCorporation($corporation); // char ID = 1
        $this->player->getCharacters()[0]->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false);
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->persist($corporation);
        $this->helper->getEm()->flush();
    }
}
