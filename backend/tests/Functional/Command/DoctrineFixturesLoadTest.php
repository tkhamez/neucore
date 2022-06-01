<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class DoctrineFixturesLoadTest extends ConsoleTestCase
{
    public function testExecute()
    {
        // setup

        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();

        $om->persist((new Role(8))->setName(Role::ESI));
        $om->persist((new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION))->setValue('1'));
        $om->persist((new SystemVariable(SystemVariable::DIRECTOR_CHAR . '1'))
            ->setScope(SystemVariable::SCOPE_SETTINGS));
        $om->flush();

        // run

        $output = explode("\n", $this->runConsoleApp('doctrine-fixtures-load'));

        $this->assertSame(4, count($output));
        $this->assertSame('loading Neucore\DataFixtures\RoleFixtureLoader', $output[0]);
        $this->assertSame('loading Neucore\DataFixtures\SystemVariablesFixtureLoader', $output[1]);
        $this->assertSame('loading Neucore\DataFixtures\MiscFixtureLoader', $output[2]);
        $this->assertSame('', $output[3]);

        $repoFactory = new RepositoryFactory($om);
        $roles = $repoFactory->getRoleRepository()->findBy([]);
        $vars = $repoFactory->getSystemVariableRepository()->findBy([], ['name' => 'asc']);

        $this->assertSame(22, count($roles)); // 21 from seed + 1 from setup
        $this->assertSame(35, count($vars)); // 34 from seed + 1 from setup

        // check that value was not changed
        $this->assertSame(SystemVariable::ALLOW_CHARACTER_DELETION, $vars[4]->getName());
        $this->assertSame('1', $vars[4]->getValue());

        $defaultEveLogin = $repoFactory->getEveLoginRepository()->findOneBy(['name' => EveLogin::NAME_DEFAULT]);
        $this->assertSame(EveLogin::NAME_DEFAULT, $defaultEveLogin->getName());
    }
}
