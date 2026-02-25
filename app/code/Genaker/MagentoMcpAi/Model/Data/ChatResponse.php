<?php
namespace Genaker\MagentoMcpAi\Model\Data;

use Genaker\MagentoMcpAi\Api\Data\ChatResponseInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

class ChatResponse extends AbstractExtensibleModel implements ChatResponseInterface
{
    /**
     * @inheritDoc
     */
    public function getSuccess()
    {
        return $this->getData('success');
    }

    /**
     * @inheritDoc
     */
    public function setSuccess($success)
    {
        return $this->setData('success', $success);
    }

    /**
     * @inheritDoc
     */
    public function getMessage()
    {
        return $this->getData('message');
    }

    /**
     * @inheritDoc
     */
    public function setMessage($message)
    {
        return $this->setData('message', $message);
    }
} 