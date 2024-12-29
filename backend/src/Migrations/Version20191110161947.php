<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\SystemVariable;

/** @noinspection PhpUnused */
final class Version20191110161947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            [SystemVariable::MAIL_INVALID_TOKEN_ACTIVE, 'mail_account_disabled_active'],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            [SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES, 'mail_account_disabled_alliances'],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            [SystemVariable::MAIL_INVALID_TOKEN_SUBJECT, 'mail_account_disabled_subject'],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            [SystemVariable::MAIL_INVALID_TOKEN_BODY, 'mail_account_disabled_body'],
        );

        // deactivate mail because it is now sent more often
        $this->addSql(
            'UPDATE system_variables SET value = ? WHERE name = ?',
            [0, SystemVariable::MAIL_INVALID_TOKEN_ACTIVE],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            ['mail_account_disabled_active', SystemVariable::MAIL_INVALID_TOKEN_ACTIVE],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            ['mail_account_disabled_alliances', SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            ['mail_account_disabled_subject', SystemVariable::MAIL_INVALID_TOKEN_SUBJECT],
        );
        $this->addSql(
            'UPDATE system_variables SET name = ? WHERE name = ?',
            ['mail_account_disabled_body', SystemVariable::MAIL_INVALID_TOKEN_BODY],
        );
    }
}
