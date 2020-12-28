<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Tests\Functional\WebTestCase;
use Tests\Helper;

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
     * @var int
     */
    private $s1Id;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $this->em = $this->helper->getEm();
    }

    public function testGet403()
    {
        $response = $this->runApp('GET', '/api/user/service/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGet404()
    {
        $this->setupDb();
        $this->loginUser(2); // role: USER

        $response = $this->runApp('GET', '/api/user/service/'.($this->s1Id + 100));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGet200()
    {
        $this->setupDb();
        $this->loginUser(2); // role: USER

        $response = $this->runApp('GET', '/api/user/service/'.$this->s1Id);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            ['id' => $this->s1Id, 'name' => 'S1', 'configuration' => ''],
            $this->parseJsonBody($response)
        );
    }

    private function setupDb(): void
    {
        $this->helper->addCharacterMain('User2', 2, [Role::USER]);

        $service = (new Service())->setName('S1');
        $this->em->persist($service);
        $this->em->flush();

        $this->s1Id = $service->getId();
    }
}
