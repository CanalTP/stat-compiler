<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20151021122022 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // Add end_point_id column from stat_compiled.requests_calls table
        $this->addSql('ALTER TABLE stat_compiled.requests_calls ADD end_point_id integer DEFAULT 1;');

        // requests_calls_pkey removed. (Duplicate values are presents)
        $this->addSql('ALTER TABLE stat_compiled.requests_calls DROP CONSTRAINT requests_calls_pkey;');

        // requests_calls_indexes added.
        $this->addSql('CREATE INDEX requests_calls_region_id_api_request_date_idx ON stat_compiled.requests_calls (region_id, api, request_date);');
        $this->addSql('CREATE INDEX requests_calls_user_id_request_date_idx ON stat_compiled.requests_calls (user_id, request_date);');
        $this->addSql('CREATE INDEX requests_calls_end_point_id_request_date_idx ON stat_compiled.requests_calls (end_point_id, request_date);');

        // New version of the partition auto-creation for requests_calls
        $this->addSql('
          CREATE OR REPLACE FUNCTION requests_calls_insert_trigger()
            RETURNS trigger AS $$
              DECLARE
                schema VARCHAR(100);
                partition VARCHAR(100);
              BEGIN
                schema := \'stat_compiled\';
                partition := \'requests_calls\' || \'_\' || to_char(NEW.request_date, \'"y"YYYY"m"MM\');
                IF NOT EXISTS(SELECT 1 FROM pg_tables WHERE tablename=partition and schemaname=schema) THEN
                  RAISE NOTICE \'A partition has been created %\',partition;
                  EXECUTE \'CREATE TABLE IF NOT EXISTS \' || schema || \'.\' || partition ||
                          \' (check (request_date >= DATE \'\'\' || to_char(NEW.request_date, \'YYYY-MM-01\') || \'\'\'
                              AND request_date < DATE \'\'\' || to_char(NEW.request_date + interval \'1 month\', \'YYYY-MM-01\') || \'\'\') ) \' ||
                          \'INHERITS (\' || schema || \'.requests_calls);\';
                  EXECUTE \'CREATE INDEX \' || partition || \'_region_id_api_request_date_idx ON \' || schema || \'.\' || partition || \' (region_id, api, request_date);\';
                  EXECUTE \'CREATE INDEX \' || partition || \'_user_id_request_date_idx ON \' || schema || \'.\' || partition || \' (user_id, request_date);\';
                  EXECUTE \'CREATE INDEX \' || partition || \'_end_point_id_request_date_idx ON \' || schema || \'.\' || partition || \' (end_point_id, request_date);\';
                END IF;
                EXECUTE \'INSERT INTO \' || schema || \'.\' || partition || \' SELECT(\' || schema || \'.requests_calls\' || \' \' || quote_literal(NEW) || \').*;\';
                RETURN NULL;
              END;
              $$
            LANGUAGE plpgsql;
        ');

        // Update pkey on all existing partitions of requests_calls
        $this->addSql('
            DO $$DECLARE
              requests_calls_partitions CURSOR FOR SELECT tablename FROM pg_tables WHERE tablename like \'requests_calls_%\' and schemaname=\'stat_compiled\' ORDER BY tablename;
            BEGIN
              RAISE NOTICE \'Starting ...\';
              FOR partition IN requests_calls_partitions LOOP
                RAISE NOTICE \'Partition: %s ...\', quote_ident(partition.tablename);
                EXECUTE \'ALTER TABLE stat_compiled.\' || partition.tablename || \' DROP CONSTRAINT \' || partition.tablename || \'_pkey;\';
                EXECUTE \'CREATE INDEX \' || partition.tablename || \'_region_id_api_request_date_idx ON stat_compiled.\' || partition.tablename || \' (region_id, api, request_date);\';
                EXECUTE \'CREATE INDEX \' || partition.tablename || \'_user_id_request_date_idx ON stat_compiled.\' || partition.tablename || \' (user_id, request_date);\';
                EXECUTE \'CREATE INDEX \' || partition.tablename || \'_end_point_id_request_date_idx ON stat_compiled.\' || partition.tablename || \' (end_point_id, request_date);\';
              END LOOP;
            END$$;
        ');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // Update Primary key on requests_calls table (host added)
        $this->addSql('CREATE UNIQUE INDEX add_host_id_temp_idx ON stat_compiled.requests_calls (region_id, api, request_date, user_id, app_name, host);');
        $this->addSql('ALTER TABLE stat_compiled.requests_calls ADD CONSTRAINT requests_calls_pkey PRIMARY KEY USING INDEX add_host_id_temp_idx;');

        // New version of the partition auto-creation for requests_calls
        $this->addSql('
          CREATE OR REPLACE FUNCTION requests_calls_insert_trigger()
            RETURNS trigger AS $$
              DECLARE
                schema VARCHAR(100);
                partition VARCHAR(100);
              BEGIN
                schema := \'stat_compiled\';
                partition := \'requests_calls\' || \'_\' || to_char(NEW.request_date, \'"y"YYYY"m"MM\');
                IF NOT EXISTS(SELECT 1 FROM pg_tables WHERE tablename=partition and schemaname=schema) THEN
                  RAISE NOTICE \'A partition has been created %\',partition;
                  EXECUTE \'CREATE TABLE IF NOT EXISTS \' || schema || \'.\' || partition ||
                          \' (CONSTRAINT \' || partition || \'_pkey PRIMARY KEY (region_id, api, request_date, user_id, app_name, host),
                            check (request_date >= DATE \'\'\' || to_char(NEW.request_date, \'YYYY-MM-01\') || \'\'\'
                                    AND request_date < DATE \'\'\' || to_char(NEW.request_date + interval \'1 month\', \'YYYY-MM-01\') || \'\'\') ) \' ||
                          \'INHERITS (\' || schema || \'.requests_calls);\';
                END IF;
                EXECUTE \'INSERT INTO \' || schema || \'.\' || partition || \' SELECT(\' || schema || \'.requests_calls\' || \' \' || quote_literal(NEW) || \').*;\';
                RETURN NULL;
              END;
              $$
            LANGUAGE plpgsql;
        ');

        // Update pkey on all existing partitions of requests_calls
        $this->addSql('
            DO $$DECLARE
              requests_calls_partitions CURSOR FOR SELECT tablename FROM pg_tables WHERE tablename like \'requests_calls_%\' and schemaname=\'stat_compiled\' ORDER BY tablename;
            BEGIN
              RAISE NOTICE \'Starting ...\';
              FOR partition IN requests_calls_partitions LOOP
                RAISE NOTICE \'Partition: %s ...\', quote_ident(partition.tablename);
                EXECUTE \'CREATE UNIQUE INDEX \' || partition.tablename || \'_add_host_id_temp_idx ON stat_compiled.\' || partition.tablename || \' (region_id, api, request_date, user_id, app_name, host);\';
                EXECUTE \'ALTER TABLE stat_compiled.\' || partition.tablename || \' ADD CONSTRAINT \' || partition.tablename || \'_pkey PRIMARY KEY USING INDEX \' || partition.tablename || \'_add_host_id_temp_idx;\';
                EXECUTE \'DROP INDEX stat_compiled.\' || partition.tablename || \'_region_id_api_request_date_idx;\';
                EXECUTE \'DROP INDEX stat_compiled.\' || partition.tablename || \'_user_id_request_date_idx;\';
                EXECUTE \'DROP INDEX stat_compiled.\' || partition.tablename || \'_end_point_id_request_date_idx;\';
              END LOOP;
            END$$;
        ');

        // requests_calls_indexes added.
        $this->addSql('DROP INDEX stat_compiled.requests_calls_region_id_api_request_date_idx;');
        $this->addSql('DROP INDEX stat_compiled.requests_calls_user_id_request_date_idx;');
        $this->addSql('DROP INDEX stat_compiled.requests_calls_end_point_id_request_date_idx;');

        // Remove end_point_id column from stat_compiled.requests_calls table
        $this->addSql('ALTER TABLE stat_compiled.requests_calls DROP COLUMN end_point_id;');


    }
}
