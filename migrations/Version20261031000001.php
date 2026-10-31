<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark registered channels as official network channels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registered_channels ADD COLUMN official BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registered_channels DROP COLUMN official');
    }
}
