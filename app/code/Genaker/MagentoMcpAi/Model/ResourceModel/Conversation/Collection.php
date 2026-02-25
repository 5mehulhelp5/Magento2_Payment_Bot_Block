<?php
namespace Genaker\MagentoMcpAi\Model\ResourceModel\Conversation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Genaker\MagentoMcpAi\Model\Conversation as ConversationModel;
use Genaker\MagentoMcpAi\Model\ResourceModel\Conversation as ConversationResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ConversationModel::class, ConversationResource::class);
    }
    
    /**
     * Get active conversations
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        $this->addFieldToFilter('status', ConversationModel::STATUS_ACTIVE);
        return $this;
    }
    
    /**
     * Get inactive conversations (no activity for specified time)
     *
     * @param int $inactiveMinutes
     * @return $this
     */
    public function addInactiveFilter($inactiveMinutes = 15)
    {
        $cutoffTime = date('Y-m-d H:i:s', time() - ($inactiveMinutes * 60));
        $this->addFieldToFilter('status', ConversationModel::STATUS_ACTIVE);
        $this->addFieldToFilter('last_activity_at', ['lt' => $cutoffTime]);
        return $this;
    }
}
