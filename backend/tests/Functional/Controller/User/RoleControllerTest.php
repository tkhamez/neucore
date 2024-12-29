<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class RoleControllerTest extends WebTestCase
{
    private Helper $helper;

    private EntityManagerInterface $em;

    private RepositoryFactory $repositoryFactory;

    private int $group1;

    private int $group2;

    protected function setUp(): void
    {
        $_SESSION = null;
        $this->helper = new Helper();
        $this->em = $this->helper->getEm();
        $this->repositoryFactory = new RepositoryFactory($this->em);
    }

    public function testGetRequiredGroups403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/role/user-chars/required-groups');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a user admin
        $response2 = $this->runApp('GET', '/api/user/role/user-chars/required-groups');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGetRequiredGroups404()
    {
        $this->setupDb();

        $this->loginUser(8); // user admin
        $response = $this->runApp('GET', '/api/user/role/does-not-exist/required-groups');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetRequiredGroups200()
    {
        $this->setupDb();

        $this->loginUser(8);
        $response = $this->runApp('GET', '/api/user/role/user-chars/required-groups');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->group1, 'name' => 'group-1', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]],
            $this->parseJsonBody($response),
        );
    }

    public function testAddRequiredGroups403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/role/user-chars/add-required-group/' . $this->group2);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/add-required-group/' . $this->group2);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testAddRequiredGroups403_WrongRole()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/role/user/add-required-group/' . $this->group2);
        $this->assertEquals(403, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/role/tracking/add-required-group/' . $this->group2);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testAddRequiredGroups404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/role/does-not-exist/add-required-group/' . $this->group2);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/add-required-group/' . ($this->group2 + 5));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddRequiredGroups204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/role/user-chars/add-required-group/' . $this->group2);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/add-required-group/' . $this->group2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $role = $this->repositoryFactory->getRoleRepository()->find(2);
        $actual = $role->getRequiredGroups();
        $this->assertSame(2, count($actual));
        $this->assertSame($this->group1, $actual[0]->getId());
        $this->assertSame($this->group2, $actual[1]->getId());
    }

    public function testRemoveRequiredGroups403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/role/user-chars/remove-required-group/' . $this->group1);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/remove-required-group/' . $this->group1);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testRemoveRequiredGroups403_WrongRole()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/role/user/remove-required-group/' . $this->group1);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRequiredGroups404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/role/does-not-exist/remove-required-group/' . $this->group1);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/remove-required-group/' . ($this->group1 + 5));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveRequiredGroups204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/role/user-chars/remove-required-group/' . $this->group1);
        $response2 = $this->runApp('PUT', '/api/user/role/user-chars/remove-required-group/' . $this->group1);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $role = $this->repositoryFactory->getRoleRepository()->find(2);
        $actual = $role->getRequiredGroups();
        $this->assertSame(0, count($actual));
    }

    private function setupDb(): void
    {
        $this->helper->emptyDb();

        $roles = $this->helper->addRoles([Role::USER, Role::USER_CHARS, Role::USER_ADMIN, Role::TRACKING]);

        $this->helper->addCharacterMain('User', 6, [Role::USER]);
        $this->helper->addCharacterMain('Admin', 8, [Role::USER, Role::USER_ADMIN]);

        $groups = $this->helper->addGroups(['group-1', 'group-2']);
        $this->group1 = $groups[0]->getId();
        $this->group2 = $groups[1]->getId();

        $roles[1]->addRequiredGroup($groups[0]);

        $this->em->flush();
        $this->em->clear();
    }
}
