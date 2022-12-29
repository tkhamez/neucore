<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

require_once __DIR__ . '/ServiceController/plugin1/src/TestService1.php';

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\SystemVariable;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\ServiceAccountData;
use Psr\Log\LoggerInterface;
use Tests\Functional\Controller\User\ServiceController\TestService1;
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

    // entity IDs
    private int $g1;
    private int $g2;
    private int $g7;
    private int $s1;
    private int $s2;
    private int $s3;
    private int $s4;

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
        TestService1::$throw = false;
        unset($_ENV['NEUCORE_PLUGINS_INSTALL_DIR']);
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

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s3/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(4);

        $response1 = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response1->getStatusCode());
    }

    public function testGet404()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp('GET', '/api/user/service/'.($this->s1 + 100).'/get');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGet404_NotActive()
    {
        $this->setupDb(true, false);
        $this->loginUser(4);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGet200_NotActiveAdmin()
    {
        $this->setupDb(true, false);
        $this->loginUser(4);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/get?allowAdmin=true",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGet200_DeactivatedGroups_Admin()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(4);

        $response2 = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/get?allowAdmin=true",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testGet200_MissingGroups_Admin()
    {
        $this->setupDb();
        $this->loginUser(4);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s3/get?allowAdmin=true",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s3,
                'name' => 'S3',
                'configurationDatabase' => [
                    'active' => true,
                    'requiredGroups' => [$this->g2, $this->g7],
                    'directoryName' => 'plugin3',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
                'configurationFile' => [
                    'name' => 'Test',
                    'type' => 'service',
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [PluginConfigurationFile::ACTION_UPDATE_ACCOUNT],
                    'directoryName' => 'plugin3',
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testGet200_WithoutYamlConfig()
    {
        $this->setupDb();

        $service5 = (new Service())->setName('S5');
        $this->em->persist($service5);
        $this->em->flush();
        $this->em->clear();

        // no admin
        $this->loginUser(1);

        $response1a = $this->runApp(
            'GET',
            "/api/user/service/{$service5->getId()}/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response1a->getStatusCode());

        $response1b = $this->runApp(
            'GET',
            "/api/user/service/{$service5->getId()}/get?allowAdmin=true",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response1b->getStatusCode());

        // login admin
        $this->loginUser(4);

        $response2a = $this->runApp(
            'GET',
            "/api/user/service/{$service5->getId()}/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response2a->getStatusCode());

        $response2b = $this->runApp(
            'GET',
            "/api/user/service/{$service5->getId()}/get?allowAdmin=true",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response2b->getStatusCode());
        $this->assertSame(['id' => $service5->getId(), 'name' => 'S5'], $this->parseJsonBody($response2b));
    }

    public function testGet200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/get",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s1,
                'name' => 'S1',
                'configurationDatabase' => [
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
                    'textRegister' => '',
                    'textPending' => '',
                    'configurationData' => '',
                ],
                'configurationFile' => [
                    'name' => '',
                    'type' => '',
                    'oneAccount' => false,
                    'properties' => [],
                    'showPassword' => false,
                    'actions' => [PluginConfigurationFile::ACTION_RESET_PASSWORD],
                    'URLs' => [],
                    'textTop' => '',
                    'textAccount' => '',
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

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s3/accounts",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAccounts403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/accounts",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/accounts",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/accounts",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAccounts500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s2/accounts",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testAccounts500()
    {
        $this->setupDb();
        $this->loginUser(1);

        TestService1::$throw = true;

        $response = $this->runApp(
            'GET',
            "/api/user/service/$this->s1/accounts",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s3/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRegister403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            'characterId' => 2,
            'username' => 'u2',
            'password' => 'p2',
            'email' => 'e2',
            'status' => ServiceAccountData::STATUS_ACTIVE,
            'name' => 'dn',
        ], $this->parseJsonBody($response));
        $this->assertEquals([new CoreGroup($this->g1, 'G1')], TestService1::$lastGroups);
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

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], TestService1::$lastGroups);
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

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s4/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"second_account"', $response->getBody()->__toString());
    }

    public function testRegister409_RegisterFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        // and add a group, so that TestService1 will throw an exception
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $group5 = (new Group())->setName('G5');
        $this->player->addGroup($group5);
        $this->em->persist($group5);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('"test"', $response->getBody()->__toString());
    }

    public function testRegister500_RegisterFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        // and add a group, so that TestService1 will throw an exception
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $group3 = (new Group())->setName('G3');
        $this->player->addGroup($group3);
        $this->em->persist($group3);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRegister500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s2/register",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testRegister500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        // change main, so that a new account can be added
        TestService1::$throw = true;
        $this->player->getCharacters()[0]->setMain(false);
        $this->player->getCharacters()[1]->setMain(true);
        $this->em->flush();
        $this->em->clear();

        $response = $this->runApp(
            'POST',
            "/api/user/service/$this->s1/register",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s3/update-account/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateAccount403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/7",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/2",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateAccount204()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals([new CoreGroup($this->g1, 'G1')], TestService1::$lastGroups);
    }

    public function testUpdateAccount204_DeactivatedGroups()
    {
        $this->setupDb(true);
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals([], TestService1::$lastGroups);
    }

    public function testUpdateAccount500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s2/update-account/1",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testUpdateAccount500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        TestService1::$throw = true;

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUpdateAccount500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/update-account/3",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
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

        $response = $this->runApp(
            'PUT',
            '/api/user/service/update-all-accounts/'.$this->player->getId(),
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $this->parseJsonBody($response));
        $this->assertSame(
            ['ServiceController::updateAllAccounts: S3: '],
            $this->log->getMessages()
        );
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

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s3/reset-password/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testResetPassword403_DeactivatedGroups()
    {
        $this->setupDb();
        $this->setupDeactivateAccount();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
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

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/7",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword404_NoAccount()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/2",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPassword200()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/1",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('new-pass', $this->parseJsonBody($response));
    }

    public function testResetPassword500_NoServiceImplementation()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s2/reset-password/1",
            null,
            null,
            [LoggerInterface::class => $this->log],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(self::ERROR_NO_SERVICE_OBJECT, $this->log->getHandler()->getRecords()[0]['message']);
    }

    public function testResetPassword500_GetAccountsFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        TestService1::$throw = true;

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/1",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']],
        );
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testResetPassword500_ChangePasswordFailed()
    {
        $this->setupDb();
        $this->loginUser(1);

        $response = $this->runApp(
            'PUT',
            "/api/user/service/$this->s1/reset-password/3",
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/ServiceController']]
        );
        $this->assertEquals(500, $response->getStatusCode());
    }

    private function setupDb(bool $noRequiredGroupsForService1 = false, bool $s1Active = true): void
    {
        $group1 = (new Group())->setName('G1');
        $group2 = (new Group())->setName('G2');
        $group7 = (new Group())->setName('G7');
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group7);
        $this->em->flush();

        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin1'; // action: reset-password
        $conf1->active = $s1Active;
        if (!$noRequiredGroupsForService1) {
            $conf1->requiredGroups = [$group1->getId()];
        }
        $service1 = (new Service())->setName('S1')->setConfigurationDatabase($conf1);

        $conf2 = new PluginConfigurationDatabase();
        $conf2->directoryName = 'plugin2';
        $conf2->active = true;
        $service2 = (new Service())->setName('S2')->setConfigurationDatabase($conf2);

        $conf3 = new PluginConfigurationDatabase();
        $conf3->directoryName = 'plugin3'; // action: update-account
        $conf3->active = true;
        $conf3->requiredGroups = [$group2->getId(), $group7->getId()];
        $service3 = (new Service())->setName('S3')->setConfigurationDatabase($conf3);

        $conf4 = new PluginConfigurationDatabase();
        $conf4->directoryName = 'plugin4'; // action: reset-password
        $conf4->active = true;
        $service4 = (new Service())->setName('S4')->setConfigurationDatabase($conf4);

        $this->em->persist($service1);
        $this->em->persist($service2);
        $this->em->persist($service3);
        $this->em->persist($service4);
        $this->em->flush();

        $this->player = $this->helper->addCharacterMain('Char1', 1, [Role::USER], [$group1->getName()])
            ->getPlayer();
        $this->helper->addCharacterToPlayer('Char2', 2, $this->player);
        $this->helper->addCharacterToPlayer('Char3', 3, $this->player);
        $this->helper->addCharacterMain('Admin', 4, [Role::USER, ROLE::SERVICE_ADMIN, Role::USER_ADMIN]);

        $this->g1 = $group1->getId();
        $this->g2 = $group2->getId();
        $this->g7 = $group7->getId();
        $this->s1 = $service1->getId();
        $this->s2 = $service2->getId();
        $this->s3 = $service3->getId();
        $this->s4 = $service4->getId();
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
