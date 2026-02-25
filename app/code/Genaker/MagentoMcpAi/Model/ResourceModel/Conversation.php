<?php
namespace Genaker\MagentoMcpAi\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Conversation extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('chatbot_conversations', 'conversation_id');
    }
}
