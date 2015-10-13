<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Creation of the coverage_modes table and associated trigger for partitions
 */
class Version20151013113000 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('
            CREATE TABLE stat_compiled.coverage_modes
            (
              region_id text NOT NULL,
              type text NOT NULL,
              mode text NOT NULL,
              commercial_mode_id text NOT NULL,
              commercial_mode_name text,
              is_internal_call integer,
              nb integer,
              request_date timestamp without time zone,
              CONSTRAINT coverage_modes_pkey PRIMARY KEY (region_id, type, mode, commercial_mode_id, commercial_mode_name, is_internal_call)
            )
            WITH (
              OIDS=FALSE
            );
        ');

        $this->addSql('
            CREATE OR REPLACE FUNCTION coverage_modes_insert_trigger()
            RETURNS TRIGGER AS $$
            DECLARE
              schema VARCHAR(100);
              partition VARCHAR(100);
            BEGIN
              schema := \'stat_compiled\';
              partition := \'coverage_modes\' || \'_\' || to_char(NEW.request_date, \'"y"YYYY"m"MM\');
              IF NOT EXISTS(SELECT 1 FROM pg_tables WHERE tablename=partition and schemaname=schema) THEN
                RAISE NOTICE \'A partition has been created %\',partition;
                EXECUTE \'CREATE TABLE IF NOT EXISTS \' || schema || \'.\' || partition ||
                        \' (CONSTRAINT \' || partition || \'_pkey PRIMARY KEY (region_id, type, mode, commercial_mode_id, commercial_mode_name, is_internal_call),
                          check (request_date >= DATE \'\'\' || to_char(NEW.request_date, \'YYYY-MM-01\') || \'\'\'
                                  AND request_date < DATE \'\'\' || to_char(NEW.request_date + interval \'1 month\', \'YYYY-MM-01\') || \'\'\') ) \' ||
                        \'INHERITS (\' || schema || \'.coverage_modes);\';
                EXECUTE \'CREATE INDEX \' || partition || \'_mode_idx ON \' || schema || \'.\' || partition || \' (mode);\';
                EXECUTE \'CREATE INDEX \' || partition || \'_type_idx ON \' || schema || \'.\' || partition || \' (type);\';
              END IF;
              EXECUTE \'INSERT INTO \' || schema || \'.\' || partition || \' SELECT(\' || schema || \'.coverage_modes\' || \' \' || quote_literal(NEW) || \').*;\';
              RETURN NULL;
            END;
            $$
            LANGUAGE plpgsql;
        ');

        $this->addSql('
            CREATE TRIGGER insert_coverage_modes_trigger
                BEFORE INSERT ON stat_compiled.coverage_modes
                FOR EACH ROW EXECUTE PROCEDURE coverage_modes_insert_trigger();
        ');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->addSql('DROP TABLE IF EXISTS stat_compiled.coverage_modes CASCADE;');
        $this->addSql('DROP FUNCTION IF EXISTS coverage_modes_insert_trigger();');
    }
}
