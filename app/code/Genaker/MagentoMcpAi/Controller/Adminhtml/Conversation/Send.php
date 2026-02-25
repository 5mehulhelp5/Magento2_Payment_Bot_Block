<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\Conversation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Genaker\MagentoMcpAi\Model\TranscriptService;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Send extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'Genaker_MagentoMcpAi::chatbot_conversations';

    /**
     * @var ConversationRepository
     */
    protected $conversationRepository;
    
    /**
     * @var TranscriptService
     */
    protected $transcriptService;

    /**
     * @param Context $context
     * @param ConversationRepository $conversationRepository
     * @param TranscriptService $transcriptService
     */
    public function __construct(
        Context $context,
        ConversationRepository $conversationRepository,
        TranscriptService $transcriptService
    ) {
        parent::__construct($context);
        $this->conversationRepository = $conversationRepository;
        $this->transcriptService = $transcriptService;
    }

    /**
     * Send conversation transcript
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            $this->messageManager->addErrorMessage(__('Conversation ID is required'));
            return $this->_redirect('*/*/index');
        }
        
        try {
            $conversation = $this->conversationRepository->getById($id);
            
            if ($conversation->getTranscriptSent()) {
                $this->messageManager->addNoticeMessage(__('Transcript has already been sent for this conversation.'));
                return $this->_redirect('*/*/view', ['id' => $id]);
            }
            
            $result = $this->transcriptService->sendTranscript($conversation);
            
            if ($result) {
                $conversation->setTranscriptSent(true);
                $this->conversationRepository->save($conversation);
                $this->messageManager->addSuccessMessage(__('Transcript has been sent successfully.'));
            } else {
                $this->messageManager->addErrorMessage(__('Failed to send transcript. Please check the support email configuration.'));
            }
            
            return $this->_redirect('*/*/view', ['id' => $id]);
            
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Conversation with ID "%1" does not exist.', $id));
            return $this->_redirect('*/*/index');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
