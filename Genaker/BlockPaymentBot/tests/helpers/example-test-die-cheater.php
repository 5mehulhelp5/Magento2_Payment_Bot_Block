#!/usr/bin/env php
<?php
/**
 * Example: Testing die() in Separate Process
 * ============================================
 * 
 * This script demonstrates how to test die() calls by running code in a separate
 * PHP process. The main test can then check the exit code and output.
 * 
 * SCENARIO: Test IP change detection that triggers die("Cheater?")
 * 
 * Usage from test:
 * ----------------
 * exec('php tests/helpers/example-test-die-cheater.php 2>&1', $output, $exitCode);
 * expect($exitCode)->not->toBe(0);
 * expect(implode('', $output))->toContain('Cheater?');
 */

// Load the hybrid bootstrap
require dirname(__DIR__) . '/bootstrap-hybrid.php';

echo "========== TESTING die() IN SEPARATE PROCESS ==========\n";

// Define test scenario
$cartId = 'test-cheater-cart-123';
$originalIP = '192.168.1.50';
$newIP = '192.168.1.100';

echo "Test Scenario:\n";
echo "├─ Cart ID: {$cartId}\n";
echo "├─ Original IP (stored in Redis): {$originalIP}\n";
echo "└─ New IP (in request): {$newIP}\n\n";

try {
    // Step 1: Setup Redis with original IP
    echo "Step 1: Setting up Redis...\n";
    $config = require BP . '/app/etc/env.php';
    $redis = new \Redis();
    
    if (!isset($config['cache']['frontend']['default']['backend_options']['server'])) {
        echo "ERROR: Redis config not found in env.php\n";
        exit(1);
    }
    
    $redis->pconnect(
        $config['cache']['frontend']['default']['backend_options']['server'],
        (int) $config['cache']['frontend']['default']['backend_options']['port']
    );
    
    // Store original IP in Redis (simulating previous request)
    $redis->set('Cart_' . $cartId . '_IP', $originalIP, 120);
    echo "├─ Stored IP {$originalIP} for cart {$cartId}\n";
    echo "└─ Redis setup complete\n\n";
    
    // Step 2: Setup $_SERVER with DIFFERENT IP
    echo "Step 2: Setting up request with different IP...\n";
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = "/rest/default/V1/guest-carts/{$cartId}/payment-information";
    $_SERVER['REMOTE_ADDR'] = $newIP;  // DIFFERENT IP!
    echo "├─ REQUEST_METHOD: POST\n";
    echo "├─ REQUEST_URI: $_SERVER[REQUEST_URI]\n";
    echo "└─ REMOTE_ADDR: {$newIP} (DIFFERENT!)\n\n";
    
    // Step 3: Setup dependencies
    echo "Step 3: Creating observer instance...\n";
    
    class TestScopeConfig implements \Magento\Framework\App\Config\ScopeConfigInterface
    {
        public function getValue($path, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
        {
            if ($path === 'checkout/block_payment_bot/active') {
                return true;
            }
            return null;
        }
        
        public function isSetFlag($path, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
        {
            return $this->getValue($path, $scope, $scopeCode) === true;
        }
    }
    
    class TestLogger implements \Psr\Log\LoggerInterface
    {
        public function emergency(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function alert(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function critical(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function error(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function warning(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function notice(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function info(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        public function debug(\Stringable|string $message, array $context = []): void { $this->log(__FUNCTION__, $message, $context); }
        
        public function log($level, \Stringable|string $message, array $context = []): void
        {
            echo "[{$level}] " . (string) $message . "\n";
        }
    }
    
    $scopeConfig = new TestScopeConfig();
    $logger = new TestLogger();
    $mockObserver = new \Magento\Framework\Event\Observer();
    
    echo "└─ Observer dependencies ready\n\n";
    
    // Step 4: Execute - THIS WILL DIE!
    echo "Step 4: Executing observer (THIS WILL DIE!)...\n";
    echo "Expected: die('Cheater?') will be called\n";
    echo "Because: IP changed from {$originalIP} to {$newIP}\n\n";
    
    $observer = new \Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore(
        $scopeConfig,
        $logger
    );
    
    // THIS LINE WILL TRIGGER die("Cheater?") because IP changed!
    $observer->execute($mockObserver);
    
    // This line should NEVER be reached
    echo "\n❌ ERROR: die() was NOT called! This should not happen.\n";
    exit(99);
    
} catch (\Throwable $e) {
    echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
    exit(2);
}

// Cleanup Redis (this won't be reached if die() is called)
$redis->del('Cart_' . $cartId . '_IP');
echo "\n✓ Test completed without die() - unexpected!\n";
exit(0);


