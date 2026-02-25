<?php
namespace Genaker\MagentoMcpAi\Model;

use Genaker\MagentoMcpAi\Api\ChatManagementInterface;
use Genaker\MagentoMcpAi\Api\Data\ChatResponseInterface;
use Genaker\MagentoMcpAi\Api\Data\ChatResponseInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

class ChatManagement implements ChatManagementInterface
{
    /**
     * @var ChatResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var McpAi
     */
    protected $mcpAi;

    /**
     * Constructor
     *
     * @param ChatResponseInterfaceFactory $responseFactory
     * @param McpAi $mcpAi
     */
    public function __construct(
        ChatResponseInterfaceFactory $responseFactory,
        McpAi $mcpAi
    ) {
        $this->responseFactory = $responseFactory;
        $this->mcpAi = $mcpAi;
    }

    /**
     * @inheritDoc
     */
    public function clearConversation($mspiApiKey)
    {
        /** @var ChatResponseInterface $response */
        $response = $this->responseFactory->create();

        try {
            // Validate MSPI API key
            if (!$this->mcpAi->validateMspiApiKey($mspiApiKey)) {
                throw new LocalizedException(__('Invalid MSPI API key provided.'));
            }

            // Clear conversation history
            $result = $this->mcpAi->clearConversationHistory($mspiApiKey);

            $response->setSuccess($result['success']);
            $response->setMessage($result['message']);
        } catch (\Exception $e) {
            $response->setSuccess(false);
            $response->setMessage($e->getMessage());
        }

        return $response;
    }
} 