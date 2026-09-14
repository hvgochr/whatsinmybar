<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep one bounded refresh rotation predecessor for concurrent requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD previous_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD rotation_grace_until INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C4BC1563 ON refresh_tokens (previous_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP previous_token_hash');
        $this->addSql('ALTER TABLE refresh_tokens DROP rotation_grace_until');
    }
}
