<?php
namespace Genaker\MagentoMcpAi\Api;

interface ChatManagementInterface
{
    /**
     * Clear chat conversation history
     *
     * @param string $mspiApiKey
     * @return \Genaker\MagentoMcpAi\Api\Data\ChatResponseInterface
     */
    public function clearConversation($mspiApiKey);
} 