<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix MCP token join table column names';
    }

    public function up(Schema $schema): void
    {
        // Drop and recreate the mcp_token_role table with correct column names
        if ($schema->hasTable('mcp_token_role')) {
            $schema->dropTable('mcp_token_role');
        }

        $mcpTokenRole = $schema->createTable('mcp_token_role');
        $mcpTokenRole->addColumn('mcp_token_id', 'integer');
        $mcpTokenRole->addColumn('role_id', 'integer');
        $mcpTokenRole->setPrimaryKey(['mcp_token_id', 'role_id']);
        $mcpTokenRole->addForeignKeyConstraint('mcp_tokens', ['mcp_token_id'], ['id'], ['onDelete' => 'CASCADE']);
        $mcpTokenRole->addForeignKeyConstraint('roles', ['role_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('mcp_token_role');
    }
}
