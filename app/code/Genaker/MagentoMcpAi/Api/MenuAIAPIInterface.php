<?php

namespace Genaker\MagentoMcpAi\Api;

interface MenuAIAPIInterface
{
    /**
     * Send a request to ChatGPT with the menu context and user query.
     *
     * @param string $userQuery
     * @param string $apiKey
     * @return array ['message' => string, 'url' => string]
     */
    public function sendRequestToChatGPT($userQuery, $apiKey);
}