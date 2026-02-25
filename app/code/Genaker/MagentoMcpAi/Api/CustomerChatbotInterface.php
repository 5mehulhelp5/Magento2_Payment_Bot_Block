<?php
namespace Genaker\MagentoMcpAi\Api;

/**
 * Interface for Customer Chatbot operations
 * @api
 */
interface CustomerChatbotInterface
{
    /**
     * Process a customer query and return a response
     *
     * @param string $query Customer's question or query
     * @param string $context Optional additional context for the query
     * @param string $apiKey API key for authentication
     * @return \Genaker\MagentoMcpAi\Api\Data\ChatResponseInterface
     */
    public function processQuery($query, $context = null, $apiKey = null);
}
