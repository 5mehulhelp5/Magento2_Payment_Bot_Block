<?php
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Console\Command;

use Genaker\BlockPaymentBot\Service\DbIntegrityService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class CheckDbIntegrity extends Command
{
    protected function configure(): void
    {
        $this->setName('genaker:blockbot:check-db-integrity')
            ->setDescription('Scan database for suspicious content patterns (XSS, injection, etc.)');
        parent::configure();
    }

    public function __construct(
        private readonly DbIntegrityService $dbIntegrityService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>BlockPaymentBot - Database Integrity Check</info>');
        $output->writeln('<info>==========================================</info>');
        $output->writeln('');

        $allFindings = [];
        $tableStats = [];

        $findings = $this->dbIntegrityService->run(function ($table, $tableFindings, $elapsed, $recordCount) use (&$tableStats, $output) {
            $tableStats[] = [
                'table' => $table,
                'findings' => count($tableFindings),
                'records' => $recordCount,
                'elapsed' => $elapsed
            ];
            if (!empty($tableFindings)) {
                $output->writeln("<comment>Scanned {$table}: {$recordCount} records in {$elapsed}s - Found " . count($tableFindings) . " suspicious pattern(s)</comment>");
            }
        });

        $allFindings = array_merge($allFindings, $findings);

        // Display summary table
        if (!empty($tableStats)) {
            $output->writeln('');
            $output->writeln('<info>Scan Summary:</info>');
            $table = new Table($output);
            $table->setHeaders(['Table', 'Records Scanned', 'Findings', 'Time (s)']);
            foreach ($tableStats as $stat) {
                $table->addRow([
                    $stat['table'],
                    $stat['records'],
                    $stat['findings'] > 0 ? '<error>' . $stat['findings'] . '</error>' : '<info>0</info>',
                    $stat['elapsed']
                ]);
            }
            $table->render();
            $output->writeln('');
        }

        if (empty($allFindings)) {
            $output->writeln('<info>No suspicious patterns detected.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>Suspicious patterns detected:</error>');
        $output->writeln('');
        foreach ($allFindings as $finding) {
            $output->writeln('<comment>' . $finding . '</comment>');
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
