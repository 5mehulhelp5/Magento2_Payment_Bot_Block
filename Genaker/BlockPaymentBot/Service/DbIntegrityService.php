<?php
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Service;

use Genaker\BlockPaymentBot\Model\IntegrityConfig;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class DbIntegrityService
{
    private const MAX_DATA_PREVIEW = 200;

    public function __construct(
        private readonly IntegrityConfig $config,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Scan DB content for suspicious patterns (eval, base64, injected JS, etc).
     *
     * @param callable(string $table, array $tableFindings, float $elapsed, int $recordCount): void|null $onTableComplete Optional callback after each table
     * @return string[] Findings (table #id field: pattern matched)
     */
    public function run(?callable $onTableComplete = null): array
    {
        $tablesConfig = $this->config->getTablesConfig();
        $patterns = $this->config->getPatterns();
        if (empty($tablesConfig) || empty($patterns)) {
            return [];
        }

        return $this->scanContent($tablesConfig, $patterns, $onTableComplete);
    }

    /**
     * @param array{table: string, id_field: string, fields: string[]}[] $tablesConfig
     * @param array{name: string, regex: string}[] $patterns
     * @param callable(string, array, float, int): void|null $onTableComplete
     * @return string[]
     */
    private function scanContent(array $tablesConfig, array $patterns, ?callable $onTableComplete): array
    {
        $connection = $this->resource->getConnection();
        $findings = [];

        foreach ($tablesConfig as $config) {
            $table = $config['table'];
            $idField = $config['id_field'];
            $fields = $config['fields'];
            if (empty($fields)) {
                continue;
            }

            $start = microtime(true);
            $tableFindings = [];

            try {
                $select = $connection->select()
                    ->from($this->resource->getTableName($table), array_merge([$idField], $fields));

                $dateField = $config['date_field'] ?? null;
                if ($dateField && $this->config->isRecentOnlyEnabled()) {
                    $days = $this->config->getRecentDays();
                    if ($days > 0) {
                        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                        $select->where($connection->quoteIdentifier($dateField) . ' >= ?', $cutoff);
                    }
                }

                $rows = $connection->fetchAll($select);
            } catch (\Throwable $e) {
                $this->logger->warning("BlockPaymentBot IntegrityCheck: Could not scan {$table}: " . $e->getMessage());
                $onTableComplete !== null && $onTableComplete($table, [], round(microtime(true) - $start, 2), 0);
                continue;
            }

            foreach ($rows as $row) {
                $id = $row[$idField];
                foreach ($fields as $field) {
                    $content = $row[$field] ?? '';
                    if ($content === '' || $content === null) {
                        continue;
                    }
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern['regex'], (string) $content) === 1) {
                            $preview = mb_substr((string) $content, 0, self::MAX_DATA_PREVIEW);
                            if (mb_strlen((string) $content) > self::MAX_DATA_PREVIEW) {
                                $preview .= '...';
                            }
                            $preview = str_replace(["\r", "\n"], ' ', $preview);
                            $finding = "{$table} #{$id} — field: {$field}\nPattern: {$pattern['name']}\nData: {$preview}";
                            $findings[] = $finding;
                            $tableFindings[] = $finding;
                        }
                    }
                }
            }

            $elapsed = round(microtime(true) - $start, 2);
            $onTableComplete !== null && $onTableComplete($table, $tableFindings, $elapsed, count($rows));
        }

        return $findings;
    }
}
