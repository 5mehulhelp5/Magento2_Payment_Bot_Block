<?php
namespace Genaker\MagentoMcpAi\Model;

use Genaker\MagentoMcpAi\Model\Conversation;
use Genaker\MagentoMcpAi\Model\ConversationFactory;
use Genaker\MagentoMcpAi\Model\ResourceModel\Conversation as ConversationResource;
use Genaker\MagentoMcpAi\Model\ResourceModel\Conversation\CollectionFactory as ConversationCollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Store\Model\StoreManagerInterface;

class ConversationRepository
{
    /**
     * @var ConversationFactory
     */
    private $conversationFactory;
    
    /**
     * @var ConversationResource
     */
    private $conversationResource;
    
    /**
     * @var ConversationCollectionFactory
     */
    private $collectionFactory;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @param ConversationFactory $conversationFactory
     * @param ConversationResource $conversationResource
     * @param ConversationCollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConversationFactory $conversationFactory,
        ConversationResource $conversationResource,
        ConversationCollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->conversationFactory = $conversationFactory;
        $this->conversationResource = $conversationResource;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
    }
    
    /**
     * Get conversation by ID
     *
     * @param int $id
     * @return Conversation
     * @throws NoSuchEntityException
     */
    public function getById($id)
    {
        $conversation = $this->conversationFactory->create();
        $this->conversationResource->load($conversation, $id);
        if (!$conversation->getId()) {
            throw new NoSuchEntityException(__('Conversation with ID "%1" does not exist.', $id));
        }
        return $conversation;
    }
    
    /**
     * Get active conversation by customer email
     *
     * @param string $email
     * @return Conversation|null
     */
    public function getActiveByEmail($email)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_email', $email)
            ->addFieldToFilter('status', Conversation::STATUS_ACTIVE)
            ->setOrder('last_activity_at', 'DESC')
            ->setPageSize(1);
            
        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }
    
    /**
     * Create new conversation
     *
     * @param string $email
     * @param string $customerName
     * @param array $additionalData
     * @return Conversation
     * @throws CouldNotSaveException
     */
    public function create($email, $customerName = null, $additionalData = [])
    {
        try {
            $conversation = $this->conversationFactory->create();
            $conversation->setCustomerEmail($email);
            $conversation->setCustomerName($customerName);
            $conversation->setStoreId($this->storeManager->getStore()->getId());
            $conversation->setStatus(Conversation::STATUS_ACTIVE);
            
            // Add IP and user agent if available
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $conversation->setIpAddress($_SERVER['REMOTE_ADDR']);
            }
            
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $conversation->setUserAgent($_SERVER['HTTP_USER_AGENT']);
            }
            
            // Set initial conversation data
            $conversation->setConversationData(json_encode(['messages' => []]));
            
            // Set any additional data
            foreach ($additionalData as $key => $value) {
                $conversation->setData($key, $value);
            }
            
            $this->conversationResource->save($conversation);
            return $conversation;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not create conversation: %1', $e->getMessage()));
        }
    }
    
    /**
     * Save conversation
     *
     * @param Conversation $conversation
     * @return Conversation
     * @throws CouldNotSaveException
     */
    public function save(Conversation $conversation)
    {
        try {
            $this->conversationResource->save($conversation);
            return $conversation;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save conversation: %1', $e->getMessage()));
        }
    }
    
    /**
     * Get all inactive conversations
     *
     * @param int $inactiveMinutes
     * @return array
     */
    public function getInactiveConversations($inactiveMinutes = 15)
    {
        $collection = $this->collectionFactory->create();
        $collection->addInactiveFilter($inactiveMinutes);
        return $collection->getItems();
    }
    
    /**
     * Delete conversation
     *
     * @param Conversation $conversation
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(Conversation $conversation)
    {
        try {
            $this->conversationResource->delete($conversation);
            return true;
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete conversation: %1', $e->getMessage())
            );
        }
    }
    
    /**
     * Delete conversation by ID
     *
     * @param int $id
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById($id)
    {
        $conversation = $this->getById($id);
        return $this->delete($conversation);
    }
}
