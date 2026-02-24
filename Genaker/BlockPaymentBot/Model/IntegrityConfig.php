<?php
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class IntegrityConfig
{
    private const XML_PATH_TABLES_CONFIG = 'checkout/block_payment_bot/integrity_tables_config';
    private const XML_PATH_PATTERNS = 'checkout/block_payment_bot/integrity_patterns';
    private const XML_PATH_RECENT_ONLY = 'checkout/block_payment_bot/integrity_recent_only';
    private const XML_PATH_RECENT_DAYS = 'checkout/block_payment_bot/integrity_recent_days';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return array{table: string, id_field: string, fields: string[]}[]
     */
    public function getTablesConfig(): array
    {
        $json = $this->scopeConfig->getValue(self::XML_PATH_TABLES_CONFIG, ScopeInterface::SCOPE_STORE);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeTablesConfig($decoded);
            }
        }
        return $this->getDefaultTablesConfig();
    }

    public function isRecentOnlyEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_RECENT_ONLY, ScopeInterface::SCOPE_STORE);
    }

    public function getRecentDays(): int
    {
        $days = (int) $this->scopeConfig->getValue(self::XML_PATH_RECENT_DAYS, ScopeInterface::SCOPE_STORE);
        return max(0, $days);
    }

    /**
     * @return array{name: string, regex: string}[]
     */
    public function getPatterns(): array
    {
        $json = $this->scopeConfig->getValue(self::XML_PATH_PATTERNS, ScopeInterface::SCOPE_STORE);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizePatterns($decoded);
            }
        }
        return $this->getDefaultPatterns();
    }

    /**
     * @param array $decoded
     * @return array{name: string, regex: string}[]
     */
    private function normalizePatterns(array $decoded): array
    {
        $result = [];
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['name']) || empty($item['regex'])) {
                continue;
            }
            $result[] = ['name' => (string) $item['name'], 'regex' => (string) $item['regex']];
        }
        return $result;
    }

    /** @return array{table: string, id_field: string, fields: string[]}[] */
    private function getDefaultTablesConfig(): array
    {
        return [
            ['table' => 'cms_page', 'id_field' => 'page_id', 'fields' => ['content', 'title'], 'date_field' => 'update_time'],
            ['table' => 'cms_block', 'id_field' => 'block_id', 'fields' => ['content'], 'date_field' => 'update_time'],
            ['table' => 'core_config_data', 'id_field' => 'config_id', 'fields' => ['value'], 'date_field' => null],
        ];
    }

    /** @return array{name: string, regex: string}[] */
    private function getDefaultPatterns(): array
    {
        return [
            ['name' => 'eval_atob', 'regex' => '/eval\s*\(\s*atob\s*\(/i'],
            ['name' => 'eval_fromCharCode', 'regex' => '/eval\s*\(\s*String\.fromCharCode\s*\(/i'],
            ['name' => 'eval_decodeURI', 'regex' => '/eval\s*\(\s*decodeURI(?:Component)?\s*\(/i'],
            ['name' => 'javascript_url', 'regex' => '/javascript\s*:/i'],
            ['name' => 'onerror_eval', 'regex' => '/on(?:error|load)\s*=\s*["\']?\s*(?:eval|javascript)/i'],
            ['name' => 'unescape', 'regex' => '/\bunescape\s*\(/i'],
        ];
    }

    private function normalizeTablesConfig(array $decoded): array
    {
        $result = [];
        foreach ($decoded as $table => $tableConfig) {
            if (!is_array($tableConfig)) {
                continue;
            }
            $idField = $tableConfig['id_field'] ?? 'id';
            $fields = $tableConfig['fields'] ?? [];
            if (is_string($fields)) {
                $fields = array_map('trim', explode(',', $fields));
            }
            $dateField = isset($tableConfig['date_field']) ? (string) $tableConfig['date_field'] : null;
            $result[] = ['table' => (string) $table, 'id_field' => (string) $idField, 'fields' => $fields, 'date_field' => $dateField ?: null];
        }
        return $result;
    }
}
