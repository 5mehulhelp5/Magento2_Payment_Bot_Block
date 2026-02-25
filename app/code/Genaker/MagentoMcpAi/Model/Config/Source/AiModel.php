<?php
namespace Genaker\MagentoMcpAi\Model\Config\Source;

class AiModel implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'gpt-3.5-turbo', 'label' => __('GPT-3.5 Turbo (Free)')],
            ['value' => 'gpt-4', 'label' => __('GPT-4 (Paid)')],
            ['value' => 'gpt-4-turbo', 'label' => __('GPT-4 Turbo (Paid)')],
            ['value' => 'gpt-4-32k', 'label' => __('GPT-4 32k (Paid)')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'gpt-3.5-turbo' => __('GPT-3.5 Turbo (Free)'),
            'gpt-4' => __('GPT-4 (Paid)'),
            'gpt-4-turbo' => __('GPT-4 Turbo (Paid)'),
            'gpt-4-32k' => __('GPT-4 32k (Paid)'),
        ];
    }
} 