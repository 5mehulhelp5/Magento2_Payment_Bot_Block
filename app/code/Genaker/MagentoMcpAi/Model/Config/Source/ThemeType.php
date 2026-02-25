<?php
namespace Genaker\MagentoMcpAi\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ThemeType implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'standard', 'label' => __('Standard Magento')],
            ['value' => 'hyva', 'label' => __('Hyva Theme (Tailwind & Alpine.js)')]
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
            'standard' => __('Standard Magento'),
            'hyva' => __('Hyva Theme (Tailwind & Alpine.js)')
        ];
    }
}
