<?php
namespace Genaker\MagentoMcpAi\Model;

use Magento\Framework\Model\AbstractModel;
use Genaker\MagentoMcpAi\Model\ResourceModel\Conversation as ConversationResource;

class Conversation extends AbstractModel
{
    /**
     * Conversation statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ConversationResource::class);
    }
    
    /**
     * Add message to the conversation
     *
     * @param string $content
     * @param bool $isFromCustomer
     * @param int|null $tokensUsed
     * @return $this
     */
    public function addMessage($content, $isFromCustomer = true, $tokensUsed = null)
    {
        $messages = $this->getMessages();
        $messages[] = [
            'content' => $content,
            'is_from_customer' => $isFromCustomer,
            'tokens_used' => $tokensUsed,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->setConversationData(json_encode(['messages' => $messages]));
        $this->setLastActivityAt(date('Y-m-d H:i:s'));
        return $this;
    }
    
    /**
     * Get conversation messages
     *
     * @return array
     */
    public function getMessages()
    {
        $data = $this->getConversationData();
        if (!$data) {
            return [];
        }
        
        $decodedData = json_decode($data, true);
        return $decodedData['messages'] ?? [];
    }
    
    /**
     * Check if conversation is inactive (no activity for specified time)
     *
     * @param int $inactiveMinutes Number of minutes to consider conversation inactive
     * @return bool
     */
    public function isInactive($inactiveMinutes = 15)
    {
        $lastActivity = strtotime($this->getLastActivityAt());
        $cutoff = time() - ($inactiveMinutes * 60);
        
        return $lastActivity < $cutoff;
    }
    
    /**
     * Get conversation as formatted transcript
     *
     * @return string
     */
    public function getTranscript()
    {
        $messages = $this->getMessages();
        if (empty($messages)) {
            return '';
        }
        
        $transcript = "Chat Transcript - " . $this->getCustomerEmail() . "\n";
        $transcript .= "Started: " . $this->getStartedAt() . "\n";
        $transcript .= "Last Activity: " . $this->getLastActivityAt() . "\n\n";
        
        foreach ($messages as $message) {
            $sender = $message['is_from_customer'] ? "Customer" : "Chatbot";
            $timestamp = $message['timestamp'] ?? '';
            $transcript .= "[$timestamp] $sender: " . $message['content'] . "\n\n";
        }
        
        return $transcript;
    }
}
