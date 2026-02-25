<?php
namespace Genaker\MagentoMcpAi\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Genaker\MagentoMcpAi\Model\Conversation;
use Genaker\MagentoMcpAi\Model\TranscriptService;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Inactive implements HttpPostActionInterface
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
     * @var TranscriptService
     */
    private $transcriptService;
    
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
     * @param TranscriptService $transcriptService
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ConversationRepository $conversationRepository,
        TranscriptService $transcriptService,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->conversationRepository = $conversationRepository;
        $this->transcriptService = $transcriptService;
        $this->json = $json;
        $this->logger = $logger;
    }
    
    /**
     * Execute action and mark conversation as inactive
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        try {
            $requestData = $this->json->unserialize($this->request->getContent());
            
            if (empty($requestData['conversation_id'])) {
                throw new LocalizedException(__('Conversation ID is required'));
            }
            
            $conversationId = $requestData['conversation_id'];
            
            try {
                $conversation = $this->conversationRepository->getById($conversationId);
            } catch (NoSuchEntityException $e) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Conversation not found')
                ]);
            }
            
            // Only process active conversations
            if ($conversation->getStatus() !== Conversation::STATUS_ACTIVE) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Conversation is already closed or inactive')
                ]);
            }
            
            // Send transcript to support
            $transcriptSent = $this->transcriptService->sendTranscript($conversation);
            
            // Mark as inactive
            $conversation->setStatus(Conversation::STATUS_INACTIVE);
            if ($transcriptSent) {
                $conversation->setTranscriptSent(true);
            }
            $conversation->setClosedAt(date('Y-m-d H:i:s'));
            $this->conversationRepository->save($conversation);
            
            return $resultJson->setData([
                'success' => true,
                'transcript_sent' => $transcriptSent,
                'message' => __('Conversation marked as inactive')
            ]);
            
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error handling inactive chatbot conversation: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred while processing your request.')
            ]);
        }
    }
}
