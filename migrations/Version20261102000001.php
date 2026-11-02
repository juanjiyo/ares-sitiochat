<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the AUTOMODE user modes of registered nicks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registered_nicks ADD COLUMN auto_modes VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registered_nicks DROP COLUMN auto_modes');
    }
}
