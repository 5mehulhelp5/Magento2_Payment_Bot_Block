<?php
namespace Genaker\MagentoMcpAi\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Chat extends Template
{
    protected $scopeConfig;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $context->getScopeConfig();
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue('magentomcpai/general/api_key');
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('magentomcpai/query/execute');
    }
} 