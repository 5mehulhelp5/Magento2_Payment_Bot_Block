<?php
namespace Genaker\MagentoMcpAi\Model\Source\Conversation;

use Magento\Framework\Data\OptionSourceInterface;
use Genaker\MagentoMcpAi\Model\Conversation;

class Status implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => Conversation::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => Conversation::STATUS_INACTIVE, 'label' => __('Inactive')],
            ['value' => Conversation::STATUS_CLOSED, 'label' => __('Closed')]
        ];
    }
}
