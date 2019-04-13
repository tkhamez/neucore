<?php declare(strict_types=1);

namespace Brave\Core\DataFixtures;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class SystemVariablesFixtureLoader implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $repository = RepositoryFactory::getInstance($manager)->getSystemVariableRepository();

        $vars = [
            SystemVariable::ACCOUNT_DEACTIVATION_DELAY      => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ALLOW_CHARACTER_DELETION        => ['0', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::ALLOW_LOGIN_MANAGED             => ['0', SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ESI_ERROR_LIMIT                 => ['',  SystemVariable::SCOPE_BACKEND],
            SystemVariable::GROUPS_REQUIRE_VALID_TOKEN      => ['0', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE    => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_ACCOUNT_DISABLED_BODY      => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT   => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_CHARACTER                  => ['',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_TOKEN                      => ['',  SystemVariable::SCOPE_BACKEND],
            SystemVariable::SHOW_PREVIEW_BANNER             => ['0', SystemVariable::SCOPE_PUBLIC],
        ];

        foreach ($vars as $name => $data) {
            $var = $repository->find($name);
            if ($var === null) {
                $var = new SystemVariable($name);
                $var->setValue($data[0]);
                $manager->persist($var);
            }
            $var->setScope($data[1]);
        }

        $manager->flush();
    }
}
