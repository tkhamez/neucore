<?php

declare(strict_types=1);

namespace Neucore\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Neucore\Application;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;

class SystemVariablesFixtureLoader
{
    public function load(ObjectManager $manager): void
    {
        $repository = RepositoryFactory::getInstance($manager)->getSystemVariableRepository();

        $pathToLogos = Application::ROOT_DIR . '/../setup';
        $logoPrefix = 'data:image/svg+xml;base64,';
        $vars = [
            SystemVariable::GROUPS_REQUIRE_VALID_TOKEN          => ['0',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ACCOUNT_DEACTIVATION_DELAY          => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES      => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS   => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ACCOUNT_DEACTIVATION_ACTIVE_DAYS    => ['30', SystemVariable::SCOPE_SETTINGS],
            SystemVariable::FETCH_STRUCTURE_NAME_ERROR_DAYS     => ['3=7,10=30', SystemVariable::SCOPE_SETTINGS],
            SystemVariable::ALLOW_CHARACTER_DELETION            => ['0',  SystemVariable::SCOPE_PUBLIC],
            SystemVariable::ALLOW_LOGIN_NO_SCOPES               => ['0',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::DISABLE_ALT_LOGIN                   => ['0',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_INVALID_TOKEN_ACTIVE           => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES        => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS     => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_INVALID_TOKEN_BODY             => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_INVALID_TOKEN_SUBJECT          => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE       => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_MISSING_CHARACTER_BODY         => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT      => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_MISSING_CHARACTER_RESEND       => ['0',  SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_CHARACTER                      => ['',   SystemVariable::SCOPE_SETTINGS],
            SystemVariable::MAIL_TOKEN                          => ['',   SystemVariable::SCOPE_BACKEND],
            SystemVariable::CUSTOMIZATION_DOCUMENT_TITLE        => [
                'Neucore - Alliance Core Services',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_WEBSITE               => [
                'https://github.com/tkhamez/neucore',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_NAV_TITLE             => ['Neucore', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_NAV_LOGO              => [
                $logoPrefix . base64_encode((string) file_get_contents($pathToLogos . '/logo-small.svg')),
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_HEADLINE         => [
                'Neucore - Alliance Core Services',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_DESCRIPTION      => [
                'An application for EVE Online communities to organise their members into groups, monitor them and ' .
                    'provide access to external services.',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_HOME_LOGO             => [
                $logoPrefix . base64_encode((string) file_get_contents($pathToLogos . '/logo.svg')),
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::CUSTOMIZATION_LOGIN_TEXT            => ['', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_HOME_MARKDOWN         => ['', SystemVariable::SCOPE_PUBLIC],
            SystemVariable::CUSTOMIZATION_FOOTER_TEXT           => [
                'Documentation is available on GitHub.',
                SystemVariable::SCOPE_PUBLIC
            ],
            SystemVariable::RATE_LIMIT_APP_MAX_REQUESTS         => ['', SystemVariable::SCOPE_SETTINGS],
            SystemVariable::RATE_LIMIT_APP_RESET_TIME           => ['', SystemVariable::SCOPE_SETTINGS],
            SystemVariable::RATE_LIMIT_APP_ACTIVE               => ['', SystemVariable::SCOPE_SETTINGS],
        ];
        foreach ($vars as $name => $data) {
            $var = $repository->find($name);
            if ($var === null) {
                /** @noinspection PhpStrictTypeCheckingInspection */
                $var = new SystemVariable($name);
                $var->setValue($data[0]);
                $manager->persist($var);
            }
            $var->setScope($data[1]);
        }

        $varsRemove = [
            'show_preview_banner', // removed in version > 0.8.0
            'esi_error_limit', // removed in version > 1.11.5
            'customization_default_theme', // removed in v1.15.0
            'customization_github', // removed in v1.34.0
        ];
        foreach ($varsRemove as $nameRemove) {
            $varRemove = $repository->find($nameRemove);
            if ($varRemove !== null) {
                $manager->remove($varRemove);
            }
        }

        $manager->flush();
    }
}
