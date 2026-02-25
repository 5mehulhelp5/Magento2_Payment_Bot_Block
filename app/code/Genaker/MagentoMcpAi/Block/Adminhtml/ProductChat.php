<?php
namespace Genaker\MagentoMcpAi\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;

/**
 * Product Chat block
 */
class ProductChat extends Container
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_objectId = 'product_chat';
        $this->_blockGroup = 'Genaker_MagentoMcpAi';
        $this->_controller = 'adminhtml_productchat';
        
        $this->buttonList->add(
            'back',
            [
                'label' => __('Back'),
                'class' => 'back',
                'onclick' => 'window.history.back();'
            ]
        );

        $this->setTemplate('Genaker_MagentoMcpAi::product_chat.phtml');
    }

    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->prepend(__('Product Chat'));
        return parent::_prepareLayout();
    }
}
