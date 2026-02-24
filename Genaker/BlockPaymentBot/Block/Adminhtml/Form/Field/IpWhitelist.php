<?php
/**
 * Copyright © Genaker. All rights reserved.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * IP Whitelist - dynamic row with Name and IP Address columns
 */
class IpWhitelist extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('name', [
            'label' => __('Name')
        ]);
        $this->addColumn('ip', [
            'label' => __('IP Address'),
            'class' => 'required-entry'
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add IP');
    }
}
