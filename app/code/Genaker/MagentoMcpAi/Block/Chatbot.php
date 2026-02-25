<?php
namespace Genaker\MagentoMcpAi\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Chatbot extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Check if chatbot is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'magentomcpai/chatbot/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get theme type
     *
     * @return string
     */
    public function getThemeType()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/theme_type',
            ScopeInterface::SCOPE_STORE
        ) ?: 'standard';
    }

    /**
     * Check if Hyva theme should be used
     *
     * @return bool
     */
    public function isHyvaTheme()
    {
        return $this->getThemeType() === 'hyva';
    }

    /**
     * Get chatbot title
     *
     * @return string
     */
    public function getChatbotTitle()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/title',
            ScopeInterface::SCOPE_STORE
        ) ?: 'Virtual Assistant';
    }

    /**
     * Get welcome message
     *
     * @return string
     */
    public function getWelcomeMessage()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/chatbot/welcome_message',
            ScopeInterface::SCOPE_STORE
        ) ?: 'Hey there 👋 I\'m here to help you find what you need.';
    }

    /**
     * Get suggested queries
     *
     * @return array
     */
    public function getSuggestedQueries()
    {
        $queries = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/suggested_queries',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$queries) {
            return [
                'I\'m looking for product information',
                'How can I track my order?',
                'What\'s your return policy?'
            ];
        }
        
        return explode("\n", $queries);
    }

    /**
     * Get OpenAI API key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            'magentomcpai/general/api_key',
            ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * Get chatbot logo URL
     *
     * @return string
     */
    public function getLogoUrl()
    {
        $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $customLogo = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/logo',
            ScopeInterface::SCOPE_STORE
        );
        
        if ($customLogo) {
            return $mediaUrl . 'magentomcpai/chatbot/' . $customLogo;
        }
        
        return $this->getViewFileUrl('Genaker_MagentoMcpAi::images/chatbot-logo.png');
    }
    
    /**
     * Get store information for context
     *
     * @return array
     */
    public function getStoreContext()
    {
        $storeName = $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
        
        $storePhone = $this->scopeConfig->getValue(
            'general/store_information/phone',
            ScopeInterface::SCOPE_STORE
        );
        
        $storeEmail = $this->scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeInterface::SCOPE_STORE
        );
        
        return [
            'name' => $storeName,
            'phone' => $storePhone,
            'email' => $storeEmail
        ];
    }

    /**
     * Get store context as JSON
     *
     * @return string
     */
    public function getStoreContextJson()
    {
        return json_encode($this->getStoreContext());
    }

    /**
     * Get suggested queries as JSON
     * 
     * @return string
     */
    public function getSuggestedQueriesJson()
    {
        return json_encode($this->getSuggestedQueries());
    }

    /**
     * Get chat button text
     * 
     * @return string
     */
    public function getChatButtonText()
    {
        return __('Try our virtual assistant');
    }

    /**
     * Check if product question answering is enabled
     *
     * @return bool
     */
    public function isProductQuestionsEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'magentomcpai/chatbot/enable_product_answers',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get product attribute blacklist
     *
     * @return array
     */
    public function getProductAttributeBlacklist()
    {
        $blacklist = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/product_attribute_blacklist',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$blacklist) {
            // Default blacklist for sensitive attributes
            return ['cost', 'price_view', 'tier_price', 'special_price', 'wholesale_price', 
                    'msrp', 'tax_class_id', 'inventory_source', 'stock_data', 'supplier_code'];
        }
        
        return array_map('trim', explode("\n", $blacklist));
    }
    
    /**
     * Get product attribute blacklist as JSON
     *
     * @return string
     */
    public function getProductAttributeBlacklistJson()
    {
        return json_encode($this->getProductAttributeBlacklist());
    }
    
    /**
     * Check if email is required before chat
     *
     * @return bool
     */
    public function isEmailRequired()
    {
        return $this->scopeConfig->isSetFlag(
            'magentomcpai/chatbot/require_email',
            ScopeInterface::SCOPE_STORE
        );
    }
    
    /**
     * Get email collection message
     *
     * @return string
     */
    public function getEmailCollectionMessage()
    {
        $message = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/email_collection_message',
            ScopeInterface::SCOPE_STORE
        );
        
        return $message ?: __('To connect you with our team and ensure follow-up, please share your email.');
    }
    
    /**
     * Get support email
     *
     * @return string
     */
    public function getSupportEmail()
    {
        $email = $this->scopeConfig->getValue(
            'magentomcpai/chatbot/support_email',
            ScopeInterface::SCOPE_STORE
        );
        
        if (!$email) {
            // Fall back to store general contact email
            $email = $this->scopeConfig->getValue(
                'trans_email/ident_general/email',
                ScopeInterface::SCOPE_STORE
            );
        }
        
        return $email;
    }
    
    /**
     * Get inactive timeout in minutes
     *
     * @return int
     */
    public function getInactiveTimeout()
    {
        $timeout = (int)$this->scopeConfig->getValue(
            'magentomcpai/chatbot/inactive_timeout',
            ScopeInterface::SCOPE_STORE
        );
        
        return $timeout > 0 ? $timeout : 15; // Default to 15 minutes
    }
}
