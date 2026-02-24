<?php
/**
 * Copyright © Genaker. All rights reserved.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Controller\Adminhtml\BlockedIps;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Genaker_BlockPaymentBot::blocked_ips';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Genaker_BlockPaymentBot::blocked_ips');
        $resultPage->getConfig()->getTitle()->prepend(__('Blocked IP Addresses'));

        return $resultPage;
    }
}
