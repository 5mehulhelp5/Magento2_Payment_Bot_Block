<?php
namespace Genaker\MagentoMcpAi\Model;

use Genaker\MagentoMcpAi\Model\Conversation;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class TranscriptService
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    
    /**
     * @var StateInterface
     */
    private $inlineTranslation;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }
    
    /**
     * Send conversation transcript to support email
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function sendTranscript(Conversation $conversation)
    {
        try {
            $storeId = $conversation->getStoreId();
            $store = $this->storeManager->getStore($storeId);
            
            // Get support email from config
            $supportEmail = $this->scopeConfig->getValue(
                'magentomcpai/chatbot/support_email',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            // If no support email configured, use general contact email
            if (!$supportEmail) {
                $supportEmail = $this->scopeConfig->getValue(
                    'trans_email/ident_general/email',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            }
            
            $supportName = $this->scopeConfig->getValue(
                'trans_email/ident_general/name',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            
            if (!$supportEmail) {
                $this->logger->error('Cannot send chatbot transcript: no support email configured');
                return false;
            }
            
            // Prepare email template variables
            $templateVars = [
                'conversation_id' => $conversation->getId(),
                'customer_email' => $conversation->getCustomerEmail(),
                'customer_name' => $conversation->getCustomerName() ?: $conversation->getCustomerEmail(),
                'started_at' => $conversation->getStartedAt(),
                'last_activity_at' => $conversation->getLastActivityAt(),
                'transcript' => $conversation->getTranscript(),
                'store_name' => $store->getName()
            ];
            
            $this->inlineTranslation->suspend();
            
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('chatbot_transcript_template')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $storeId
                ])
                ->setTemplateVars($templateVars)
                ->setFrom(['email' => $supportEmail, 'name' => $supportName])
                ->addTo($supportEmail, $supportName)
                ->getTransport();
                
            $transport->sendMessage();
            $this->inlineTranslation->resume();
            
            $conversation->setTranscriptSent(true);
            $conversation->setStatus(Conversation::STATUS_INACTIVE);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send chatbot transcript: ' . $e->getMessage());
            return false;
        }
    }
}
