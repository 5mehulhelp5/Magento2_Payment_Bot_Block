<?php
namespace Genaker\MagentoMcpAi\Api;

/**
 * Interface for MCP AI operations
 * @api
 */
interface McpAiInterface
{
    /**
     * Generate and execute SQL query based on natural language prompt
     *
     * @param string $prompt
     * @param string $model
     * @param string $mspiApiKey
     * @return any
     */
    public function generateQuery($prompt, $model = 'gpt-3.5-turbo', $mspiApiKey = null);
    
    /**
     * Execute SQL query
     *
     * @param string $query
     * @return any
     */
    public function executeQuery($query);

    /**
     * Clear the conversation history for a specific API key
     *
     * @param string|null $mspiApiKey
     * @return array
     */
    public function clearConversationHistory($mspiApiKey);
} 