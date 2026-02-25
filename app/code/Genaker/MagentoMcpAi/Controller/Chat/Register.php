<?php
namespace Genaker\MagentoMcpAi\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Register implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;
    
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    
    /**
     * @var ConversationRepository
     */
    private $conversationRepository;
    
    /**
     * @var Json
     */
    private $json;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param ConversationRepository $conversationRepository
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ConversationRepository $conversationRepository,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->conversationRepository = $conversationRepository;
        $this->json = $json;
        $this->logger = $logger;
    }
    
    /**
     * Execute action and register customer email
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        try {
            $requestData = $this->json->unserialize($this->request->getContent());
            
            if (empty($requestData['email'])) {
                throw new LocalizedException(__('Email is required'));
            }
            
            $email = $requestData['email'];
            
            // Basic email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Invalid email format'));
            }
            
            // Check if there's an existing active conversation
            $existingConversation = $this->conversationRepository->getActiveByEmail($email);
            
            if ($existingConversation) {
                // Use existing conversation
                $conversationId = $existingConversation->getId();
            } else {
                // Create new conversation
                $storeId = $requestData['store_id'] ?? 0;
                $customerName = $requestData['name'] ?? null;
                
                $conversation = $this->conversationRepository->create(
                    $email,
                    $customerName,
                    ['store_id' => $storeId]
                );
                
                $conversationId = $conversation->getId();
            }
            
            return $resultJson->setData([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => __('Email registered successfully')
            ]);
            
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error registering chatbot email: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while registering your email. Please try again.')
            ]);
        }
    }
}
