<?php
namespace Genaker\MagentoMcpAi\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;

class Iframe extends Template
{
    const XML_PATH_API_KEY = 'magentomcpai/general/api_key';
    const XML_PATH_MSPI_API_KEY = 'magentomcpai/general/mspi_api_key';
    const LOG_FILE = 'mcpai_debug.log';

    protected $scopeConfig;
    protected $file;
    protected $directoryList;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        File $file,
        DirectoryList $directoryList,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
        $this->file = $file;
        $this->directoryList = $directoryList;
        
        // Disable block caching
        $this->setData('cache_lifetime', null);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Genaker_MagentoMcpAi::iframe.phtml');
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getMspiApiKey()
    {
        $key = $this->scopeConfig->getValue(
            self::XML_PATH_MSPI_API_KEY,
            ScopeInterface::SCOPE_STORE
        );

        return $key;
    }
} 