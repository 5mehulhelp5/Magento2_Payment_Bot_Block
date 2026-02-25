<?php
namespace Genaker\MagentoMcpAi\Model;

use Magento\Framework\Exception\LocalizedException;

class QueryValidator
{
    private const ALLOWED_COMMANDS = ['SELECT', 'DESCRIBE', 'SHOW'];
    
    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 
        'RENAME', 'REPLACE', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK', 'MODIFY',
        'EXEC', 'EXECUTE', 'PREPARE', 'HANDLER', 'LOAD', 'RESET', 'PURGE',
        'BACKUP', 'RESTORE', 'KILL', 'SHUTDOWN'
    ];
    
    /**
     * Check if a query is valid without throwing exceptions
     *
     * @param string $query The SQL query to validate
     * @return bool True if the query is valid, false otherwise
     */
    public function isValid($query)
    {
        try {
            $this->validate($query);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validates a SQL query and throws exceptions for invalid queries
     *
     * @param string $query The SQL query to validate
     * @return bool Always returns true if validation passes
     * @throws LocalizedException If the query is invalid
     */
    public function validate($query)
    {
        $query = $this->removeComments($query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
        $upperQuery = strtoupper($query);

        if (strpos($query, ';') !== false) {
            throw new LocalizedException(__('Multiple queries are not allowed'));
        }

        $startsWithAllowed = false;
        foreach (self::ALLOWED_COMMANDS as $command) {
            if (strpos($upperQuery, $command) === 0) {
                $startsWithAllowed = true;
                break;
            }
        }

        if (!$startsWithAllowed) {
            throw new LocalizedException(__('Query must start with SELECT, DESCRIBE, or SHOW'));
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/', $upperQuery)) {
                throw new LocalizedException(__('Query contains forbidden keyword: %1', $keyword));
            }
        }

        if (strlen($query) > 4096) {
            throw new LocalizedException(__('Query is too long'));
        }

        return true;
    }

    private function removeComments($query)
    {
        return preg_replace(['/--.*$/m', '/\/\*.*?\*\//s'], '', $query);
    }
} 