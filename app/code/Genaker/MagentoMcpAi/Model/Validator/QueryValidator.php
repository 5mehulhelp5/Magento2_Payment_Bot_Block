<?php
namespace Genaker\MagentoMcpAi\Model\Validator;

use Magento\Framework\Exception\LocalizedException;

class QueryValidator
{
    /**
     * Validate SQL query
     *
     * @param string $query
     * @return bool
     * @throws LocalizedException
     */
    public function validate($query)
    {
        // Check if query is empty
        if (empty($query)) {
            throw new LocalizedException(__('Query cannot be empty'));
        }

        // Check query length
        if (strlen($query) > 4096) {
            throw new LocalizedException(__('Query is too long. Maximum length is 4096 characters'));
        }

        // Check for dangerous SQL operations
        $dangerousOperations = [
            'DROP ',
            'DELETE ',
            'UPDATE ',
            'INSERT ',
            'TRUNCATE ',
            'ALTER ',
            'CREATE ',
            'GRANT ',
            'REVOKE ',
            'ALTER TABLE ',
            'DROP TABLE ',
            'CREATE TABLE ',
            'INSERT INTO ',
            'UPDATE ',
            'DELETE '
        ];

        $queryUpper = strtoupper($query);
        foreach ($dangerousOperations as $operation) {
            if (strpos($queryUpper, $operation) !== false) {
                throw new LocalizedException(__('Query contains dangerous operation: %1', $operation));
            }
        }

        // Check for valid SQL query structure
        if (!preg_match('/^SELECT\s+/i', $query) && !preg_match('/^DESCRIBE\s+/i', $query) && !preg_match('/^SHOW TABLES\s+/i', $query)) {
            throw new LocalizedException(__('Only SELECT and DESCRIBE queries are allowed'));
        }

        return true;
    }
} 