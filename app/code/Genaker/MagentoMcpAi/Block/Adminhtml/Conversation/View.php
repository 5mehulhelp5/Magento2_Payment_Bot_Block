<?php
namespace Genaker\MagentoMcpAi\Block\Adminhtml\Conversation;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Magento\Framework\Registry;
use Magento\Framework\Exception\NoSuchEntityException;

class View extends Template
{
    /**
     * @var ConversationRepository
     */
    protected $conversationRepository;
    
    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @param Context $context
     * @param ConversationRepository $conversationRepository
     * @param Registry $coreRegistry
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConversationRepository $conversationRepository,
        Registry $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->conversationRepository = $conversationRepository;
        $this->coreRegistry = $coreRegistry;
    }
    
    /**
     * Get header text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getHeaderText()
    {
        $conversation = $this->getConversation();
        if ($conversation) {
            return __('Conversation #%1 - %2', $conversation->getId(), $conversation->getCustomerEmail());
        }
        return __('Conversation Details');
    }
    
    /**
     * Get current conversation
     *
     * @return \Genaker\MagentoMcpAi\Model\Conversation|null
     */
    public function getConversation()
    {
        $conversationId = $this->getRequest()->getParam('id');
        if (!$conversationId) {
            return null;
        }
        
        try {
            return $this->conversationRepository->getById($conversationId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
    
    /**
     * Get conversation messages
     *
     * @return array
     */
    public function getMessages()
    {
        $conversation = $this->getConversation();
        if (!$conversation) {
            return [];
        }
        
        $conversationData = json_decode($conversation->getConversationData(), true);
        return isset($conversationData['messages']) ? $conversationData['messages'] : [];
    }
    
    /**
     * Get formatted date
     *
     * @param string|null $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     * @return string
     */
    public function formatDate(
        $date = null,
        $format = \IntlDateFormatter::MEDIUM,
        $showTime = true,
        $timezone = null
    ) {
        if ($date instanceof \DateTimeInterface) {
            return parent::formatDate($date, $format, $showTime, $timezone);
        }
        
        if ($date) {
            $date = new \DateTime($date);
        }
        
        return parent::formatDate($date, $format, $showTime, $timezone);
    }
    
    /**
     * Get send transcript URL
     *
     * @return string
     */
    public function getSendTranscriptUrl()
    {
        $conversation = $this->getConversation();
        if ($conversation) {
            return $this->getUrl('magentomcpai/conversation/send', ['id' => $conversation->getId()]);
        }
        return '#';
    }
    
    /**
     * Check if transcript has been sent
     *
     * @return bool
     */
    public function isTranscriptSent()
    {
        $conversation = $this->getConversation();
        return $conversation ? (bool)$conversation->getTranscriptSent() : false;
    }
    
    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('magentomcpai/conversation/index');
    }
}
