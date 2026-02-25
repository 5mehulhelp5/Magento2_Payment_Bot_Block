<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\Conversation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Genaker\MagentoMcpAi\Model\ConversationRepository;
use Magento\Framework\Exception\NoSuchEntityException;

class View extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'Genaker_MagentoMcpAi::chatbot_conversations';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;
    
    /**
     * @var ConversationRepository
     */
    protected $conversationRepository;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ConversationRepository $conversationRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ConversationRepository $conversationRepository
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * View conversation
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
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(
                __('Conversation #%1 - %2', $conversation->getId(), $conversation->getCustomerEmail())
            );
            return $resultPage;
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Conversation with ID "%1" does not exist.', $id));
            return $this->_redirect('*/*/index');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred: %1', $e->getMessage()));
            return $this->_redirect('*/*/index');
        }
    }
}
