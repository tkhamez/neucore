<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create MCP tokens table';
    }

    public function up(Schema $schema): void
    {
        $mcpTokens = $schema->createTable('mcp_tokens');
        $mcpTokens->addColumn('id', 'integer', ['autoincrement' => true]);
        $mcpTokens->addColumn('name', 'string', ['length' => 255]);
        $mcpTokens->addColumn('secret', 'string', ['length' => 255]);
        $mcpTokens->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $mcpTokens->setPrimaryKey(['id']);

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
        $schema->dropTable('mcp_tokens');
    }
}
