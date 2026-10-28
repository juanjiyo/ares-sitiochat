<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add KEEPTOPIC and DEFTOPIC channel settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registered_channels ADD COLUMN keep_topic BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE registered_channels ADD COLUMN default_topic VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($this->platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE registered_channels DROP COLUMN keep_topic');
            $this->addSql('ALTER TABLE registered_channels DROP COLUMN default_topic');
        } else {
            $this->addSql('ALTER TABLE registered_channels DROP COLUMN keep_topic');
            $this->addSql('ALTER TABLE registered_channels DROP COLUMN default_topic');
        }
    }
}
