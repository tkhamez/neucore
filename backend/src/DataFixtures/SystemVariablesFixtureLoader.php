<?php declare(strict_types=1);

namespace Neucore\DataFixtures;

use Neucore\Application;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class SystemVariablesFixtureLoader implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $repository = RepositoryFactory::getInstance($manager)->getSystemVariableRepository();

        $pathToImages = Application::ROOT_DIR . '/var';
        $imagePrefix = 'data:image/png;base64,';
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
            SystemVariable::CUSTOMIZATION_DOCUMENT_TITLE    => ['Alliance Core Services', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_DEFAULT_THEME     => ['Darkly', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_WEBSITE           => [
                'https://github.com/tkhamez/neucore',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_NAV_TITLE         => ['Neucore', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_NAV_LOGO          => [
                $imagePrefix . base64_encode((string) file_get_contents($pathToImages . '/logo_29.png')),
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_HEADLINE     => ['Core Services', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_HOME_DESCRIPTION  => [
                'An application to manage access for EVE Online players to external services.',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_LOGO         => [
                $imagePrefix . base64_encode((string) file_get_contents($pathToImages . '/logo_300.png')),
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_MARKDOWN     => ['', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_FOOTER_TEXT       => [
                'Documentation is available on GitHub.',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_GITHUB            => [
                'https://github.com/tkhamez/neucore',
                SystemVariable::SCOPE_PUBLIC
            ],
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

        $varsRemove = ['show_preview_banner']; // removed in version > 0.8.0
        foreach ($varsRemove as $nameRemove) {
            $varRemove = $repository->find($nameRemove);
            if ($varRemove !== null) {
                $manager->remove($varRemove);
            }
        }

        $manager->flush();
    }
}
