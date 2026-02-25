<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\ProductChat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Index
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        
        if (!$resultPage) {
            throw new \Magento\Framework\Exception\NotFoundException(__('Page not found'));
        }

        $resultPage->setActiveMenu('Genaker_MagentoMcpAi::mcpai');
        $resultPage->getConfig()->getTitle()->prepend(__('Product Chat'));
        
        return $resultPage;
    }

    /**
     * Check permission via ACL resource
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Genaker_MagentoMcpAi::mcpai');
    }
}
