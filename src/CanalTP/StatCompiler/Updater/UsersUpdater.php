<?php
namespace CanalTP\StatCompiler\Updater;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class UsersUpdater implements UpdaterInterface
{
    use LoggerAwareTrait;

    protected $dbConnection;

    public function __construct(\PDO $dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->logger = new NullLogger();
    }

    public function getAffectedTable()
    {
        return 'users';
    }

    public function update(\DateTime $startDate, \DateTime $endDate)
    {
        $this->dbConnection->beginTransaction();

        try {
            $this->dbConnection->exec(
                " CREATE TEMPORARY TABLE tmp_users AS
                SELECT DISTINCT user_id as id, user_name, request_date
                FROM (
                    SELECT
                        user_id,
                        first_value(user_name) over (partition by user_id order by request_date DESC) as user_name,
                        request_date
                    FROM (
                        SELECT user_id, user_name, MIN(request_date) as request_date
                        FROM stat.requests
                        WHERE request_date >= ('" . $startDate->format('Y-m-d') . "' :: date)
                        AND request_date < ('" . $endDate->format('Y-m-d') . "' :: date) + interval '1 day'
                        GROUP BY user_id, user_name
                    ) b
                    GROUP BY user_id, user_name, request_date
                ) a"
            );

            $this->dbConnection->exec(
                'INSERT INTO stat_compiled.users (id, user_name, date_first_request)
                SELECT id, user_name, request_date
                FROM tmp_users tmpu
                WHERE tmpu.id NOT IN (
                    SELECT id FROM stat_compiled.users
                )'
            );

            $this->dbConnection->exec(
                'UPDATE stat_compiled.users
                SET date_first_request = tmpu.request_date
                FROM tmp_users tmpu
                WHERE tmpu.id = stat_compiled.users.id
                AND tmpu.request_date < date_first_request'
            );

            $this->dbConnection->commit();
        } catch (\PDOException $e) {
            $this->dbConnection->rollBack();
            throw new \RuntimeException("Exception occurred during update", 1, $e);
        }
    }

    public function init()
    {
        $sqlToRun = array(
            'TRUNCATE TABLE stat_compiled.users',
            'INSERT INTO stat_compiled.users (id, user_name)
            SELECT DISTINCT user_id, user_name
            FROM (
                SELECT
                    user_id,
                    first_value(user_name) over (partition by user_id order by request_date DESC) as user_name
                FROM (
                    SELECT user_id, user_name, MIN(request_date) as request_date
                    FROM stat.requests
                    GROUP BY user_id, user_name
                ) B
            ) A',
        );
        foreach ($sqlToRun as $sql) {
            $this->logger->debug('Query = ' . $sql);
            $this->dbConnection->exec($sql);
        }
    }
}
