<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160915171331 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('DROP TABLE IF EXISTS stat_compiled.journey_cities CASCADE;');
        $this->addSql('DROP FUNCTION IF EXISTS stat_compiled.journey_cities_insert_trigger();');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // Not implemented. No rollback possible
    }
}
