<?php
/**
 * Copyright © Genaker. All rights reserved.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Bot Rules dynamic row - path regex, block count, block time
 */
class BotRules extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn('path', [
            'label' => __('Path (regex)'),
            'class' => 'required-entry',
            'style' => 'width: 50%'
        ]);
        $this->addColumn('block_count', [
            'label' => __('Request Count'),
            'class' => 'validate-digits required-entry',
            'style' => 'width: 80px'
        ]);
        $this->addColumn('block_time', [
            'label' => __('Block Time (min)'),
            'class' => 'validate-digits required-entry',
            'style' => 'width: 80px'
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Rule');
    }
}
