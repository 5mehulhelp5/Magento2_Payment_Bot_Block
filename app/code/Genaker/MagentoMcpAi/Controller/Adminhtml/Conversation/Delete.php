<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\Conversation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;

class Delete extends Action
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
     * @param Context $context
     * @param ConversationRepository $conversationRepository
     */
    public function __construct(
        Context $context,
        ConversationRepository $conversationRepository
    ) {
        parent::__construct($context);
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * Delete conversation
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
            
            // Delete conversation from repository
            $this->conversationRepository->delete($conversation);
            
            $this->messageManager->addSuccessMessage(__('The conversation has been deleted.'));
            return $this->_redirect('*/*/index');
            
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Conversation with ID "%1" does not exist.', $id));
            return $this->_redirect('*/*/index');
        } catch (CouldNotDeleteException $e) {
            $this->messageManager->addErrorMessage(__('Could not delete conversation: %1', $e->getMessage()));
            return $this->_redirect('*/*/view', ['id' => $id]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
