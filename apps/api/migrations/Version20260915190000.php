<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track comment depth and index bounded chronological comment pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment ADD depth INT DEFAULT 1 NOT NULL');
        $this->addSql(<<<'SQL'
            WITH RECURSIVE comment_tree AS (
                SELECT id, 1 AS depth
                FROM comment
                WHERE parent_id IS NULL
                UNION ALL
                SELECT child.id, parent.depth + 1
                FROM comment child
                INNER JOIN comment_tree parent ON child.parent_id = parent.id
            )
            UPDATE comment
            SET depth = comment_tree.depth
            FROM comment_tree
            WHERE comment.id = comment_tree.id
            SQL);
        $this->addSql('CREATE INDEX idx_comment_recipe_created ON comment (recipe_id, created_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_comment_recipe_created');
        $this->addSql('ALTER TABLE comment DROP depth');
    }
}
