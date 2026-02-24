<?php
/**
 * Copyright © Genaker. All rights reserved.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Block\Adminhtml;

use Genaker\BlockPaymentBot\Model\BlockedIpsProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class BlockedIps extends Template
{
    public function __construct(
        Context $context,
        private readonly BlockedIpsProvider $blockedIpsProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBlockedData(): array
    {
        return $this->blockedIpsProvider->getBlockedIps();
    }

    public function formatReason(string $reason): string
    {
        $map = [
            'DIE_IP_COUNTER_AT_LIMIT' => 'IP at limit',
            'DIE_IP_COUNTER_EXCEEDED' => 'IP exceeded',
            'DIE_CART_COUNTER_AT_LIMIT' => 'Cart at limit',
            'DIE_CART_COUNTER_EXCEEDED' => 'Cart exceeded',
            'DIE_CHEATER_IP_CHANGED' => 'IP changed',
            'DIE_FORM_CHECK_FAILED' => 'Form check failed',
            'DIE_NO_CART_CHECK' => 'No cart check',
        ];
        return $map[$reason] ?? $reason;
    }

    public function formatExpiresIn(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' sec';
        }
        if ($seconds < 3600) {
            return (int)($seconds / 60) . ' min';
        }
        return round($seconds / 3600, 1) . ' hrs';
    }
}
