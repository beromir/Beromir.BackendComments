<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the node comment table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            "Migration can only be executed safely on 'Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE beromir_backendcomments_comment (
                id CHAR(36) NOT NULL PRIMARY KEY,
                content_repository_id VARCHAR(36) NOT NULL,
                node_aggregate_id VARCHAR(64) NOT NULL,
                document_node_aggregate_id VARCHAR(64) NULL,
                document_label VARCHAR(255) NULL,
                node_type VARCHAR(255) NOT NULL,
                node_label VARCHAR(255) NOT NULL,
                workspace_name VARCHAR(255) NOT NULL,
                dimension_space_point TEXT NOT NULL,
                property_name VARCHAR(255) NULL,
                author_user_id VARCHAR(40) NOT NULL,
                text TEXT NOT NULL,
                status VARCHAR(16) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                resolved_at DATETIME(6) NULL,
                INDEX idx_comment_author (content_repository_id, author_user_id, status, created_at),
                INDEX idx_comment_node (content_repository_id, node_aggregate_id),
                INDEX idx_comment_document (content_repository_id, document_node_aggregate_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE beromir_backendcomments_comment');
    }
}
