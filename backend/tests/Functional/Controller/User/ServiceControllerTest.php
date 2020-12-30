<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Controller\User\ServiceController;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;

class ServiceControllerTest extends WebTestCase
{
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
     * @var int
     */
    private $p1;

    /**
     * @var int
     */
    private $s1;

    /**
     * @var int
     */
    private $s2;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->em = $this->helper->getEm();
        $this->log = new Logger('Test');
    }

    public function testService403()
    {
        $response = $this->runApp('GET', '/api/user/service/service/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testService404()
    {
        $this->setupDb();
        $this->loginUser(1); // role: USER

        $response = $this->runApp('GET', '/api/user/service/service/'.($this->s1 + 100));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testService200()
    {
        $this->setupDb();
        $this->loginUser(1); // role: USER

        $response = $this->runApp('GET', '/api/user/service/service/'.$this->s1);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id' => $this->s1,
                'name' => 'S1',
                'configuration' => json_encode([
                    'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService'
                ])
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testServiceAccounts403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', "/api/user/service/service-accounts/{$this->s1}/{$this->p1}");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testServiceAccounts404()
    {
        $this->setupDb();
        $this->loginUser(1); // role: USER

        $response1 = $this->runApp('GET', '/api/user/service/service-accounts/'.($this->s1 + 100)."/{$this->p1}");
        $response2 = $this->runApp('GET', "/api/user/service/service-accounts/{$this->s1}/".($this->p1 + 100));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testServiceAccounts200_InvalidPhpClass()
    {
        $this->setupDb();
        $this->loginUser(1); // role: USER

        $response = $this->runApp('GET', "/api/user/service/service-accounts/{$this->s2}/{$this->p1}", null, null, [
            LoggerInterface::class => $this->log
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
        $this->assertSame(
            "ServiceController: The configured service class does not exist of does not implement ".
            "Neucore\Plugin\ServiceInterface.",
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testServiceAccounts200()
    {
        $this->setupDb();
        $this->loginUser(1); // role: USER

        $response = $this->runApp('GET', "/api/user/service/service-accounts/{$this->s1}/{$this->p1}");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['characterId' => 1, 'username' => 'u', 'password' => 'p', 'email' => 'e', 'status' => null]
        ], $this->parseJsonBody($response));
    }

    private function setupDb(): void
    {
        $service1 = (new Service())->setName('S1')->setConfiguration((string)json_encode([
            'phpClass' => 'Tests\Functional\Controller\User\ServiceControllerTest_TestService',
        ]));
        $service2 = (new Service())->setName('S1')->setConfiguration((string)json_encode([
            'phpClass' => ServiceController::class
        ]));
        $player = $this->helper->addCharacterMain('Char1', 1, [Role::USER])->getPlayer();
        $this->helper->addCharacterToPlayer('Char2', 2, $player);

        $this->em->persist($service1);
        $this->em->persist($service2);
        $this->em->flush();

        $this->s1 = $service1->getId();
        $this->s2 = $service2->getId();
        $this->p1 = $player->getId();
    }
}
