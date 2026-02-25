<?php
namespace Genaker\MagentoMcpAi\Framework;

class AiPhrase extends \Magento\Framework\Phrase
{
    // Override methods or add new functionality here
    public function __construct($text, $arguments = [])
    {
        // Custom logic
        $text = "text";//$text;
        parent::__construct($text, $arguments);
    }

    /**
     * Get phrase base text
     *
     * @return string
     */
    public function getText()
    {
        return "text";//$this->text;
    }

}