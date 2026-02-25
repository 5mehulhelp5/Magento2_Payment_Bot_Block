<?php
namespace Genaker\MagentoMcpAi\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Iframe extends Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param RawFactory $resultRawFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        RawFactory $resultRawFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->resultRawFactory = $resultRawFactory;
    }

    /**
     * Execute view action
     *
     * @return Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        
        // Create completely blank layout
        $resultPage->getConfig()->setPageLayout('empty');
        
        // Remove all default assets
        $resultPage->getLayout()->getUpdate()->addHandle('empty');
        
        // Set X-Frame-Options header to allow iframe
        $this->getResponse()->setHeader('X-Frame-Options', 'SAMEORIGIN');
        
        return $resultPage;
    }

    /**
     * Check for is allowed
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Genaker_MagentoMcpAi::mcpai');
    }
} 