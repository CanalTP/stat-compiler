<?php
namespace CanalTP\StatCompiler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use CanalTP\StatCompiler\Updater\UpdaterInterface;
use Psr\Log\LoggerAwareTrait;

class UpdateDbCommand extends Command
{
    use LoggerAwareTrait;

    private $updaters = array();

    /**
     * @var bool
     */
    private $metricsLogged = false;

    protected function configure()
    {
        $this
            ->setName('updatedb')
            ->setDescription('Update compiled stat tables')
            ->addArgument(
                'start_date',
                InputArgument::OPTIONAL,
                'Consolidation start date (YYYY-MM-DD). Defaults to yesterday.',
                date('Y-m-d', time() - 24 * 3600)
            )
            ->addArgument(
                'end_date',
                InputArgument::OPTIONAL,
                'Consolidation end date (YYYY-MM-DD). Defaults to yesterday.',
                date('Y-m-d', time() - 24 * 3600)
            )
            ->addOption(
                'only-update',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit update to given tables',
                ''
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startDate = \DateTime::createFromFormat('!Y-m-d', $input->getArgument('start_date'));
        $endDate = \DateTime::createFromFormat('!Y-m-d', $input->getArgument('end_date'));
        $this->metricsLogged = $endDate == $startDate;

        if (false === $startDate) {
            throw new \RuntimeException('Wrong start date format (' . $input->getArgument('start_date') . ') expecting YYYY-MM-DD');
        }

        if (false === $endDate) {
            throw new \RuntimeException('Wrong end date format (' . $input->getArgument('end_date') . ') expecting YYYY-MM-DD');
        }

        if ($startDate > $endDate) {
            throw new \RuntimeException('Start date (' . $input->getArgument('start_date') . ') must be before end date (' . $input->getArgument('end_date') . ')');
        }

        $this->logger->info('Starting update', array('start_date' => $startDate, 'end_date' => $endDate));
        $tables = array();
        if ('' !== $input->getOption('only-update')) {
            $tables = explode(',', $input->getOption('only-update'));
        }
        $beginExecution = new \DateTime();
        foreach ($this->updaters as $upd) {
            if (empty($tables) || in_array($upd->getAffectedTable(), $tables)) {
                $nameSpace = get_class($upd);
                $this->logger->info("Launching " . $nameSpace);
                $beginTable = new \DateTime();
                $upd->update($startDate, $endDate);
                $endTable = new \DateTime();
                $this->logMetrics($beginExecution, $startDate, $nameSpace, $endTable->getTimestamp() - $beginTable->getTimestamp());
            }
        }
        $endExecution = new \DateTime();
        $this->logMetrics($beginExecution, $startDate, 'TOTAL', $endExecution->getTimestamp() - $beginExecution->getTimestamp());
        $this->logger->info('Update ended');
    }

    private function logMetrics(\DateTime $date, \DateTime $periodDate, $nameSpace, $duration)
    {
        if ($this->metricsLogged) {
            $nameSpaceArray = explode("\\", $nameSpace);
            $updater = str_replace('Updater', '', end($nameSpaceArray));
            $this->logger->info(
                sprintf('[stat-compiler] [OK] [%s] [%s] [%s] [%d]',
                    $date->format('Y-m-d H:i:s'),
                    $periodDate->format('Y-m-d'),
                    $updater,
                    $duration
                )
            );
        }
    }

    public function addUpdater(UpdaterInterface $updater)
    {
        $this->updaters[] = $updater;
    }
}
