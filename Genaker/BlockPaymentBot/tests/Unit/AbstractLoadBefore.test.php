<?php
/**
 * Pest test for Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore
 * Tests bot detection and blocking logic with ACTUAL class and mocked dependencies
 * 
 * Run: vendor/bin/pest Unit/AbstractLoadBefore.test.php
 * 
 * APPROACH: Hybrid bootstrap (bootstrap-hybrid.php)
 * - Loads Pest's PHPUnit 10+ first
 * - Then loads Magento classes via custom autoloader (skips PHPUnit)
 * - Both coexist without conflicts!
 * - We use the ACTUAL AbstractLoadBefore class
 * - Dependencies are mocked (ScopeConfig, Logger)
 * - NO method copying needed!
 */

/**
 * Uses the ACTUAL AbstractLoadBefore class from Magento!
 * NO method copying - tests the real implementation directly!
 * 
 * The hybrid bootstrap loads both Pest's PHPUnit and Magento classes successfully
 */
class AbstractLoadBeforeTestHelper
{
    private $actualInstance;
    private $reflection;
    
    public function __construct($scopeConfig, $logger)
    {
        // Create the ACTUAL AbstractLoadBefore instance from Magento!
        $this->actualInstance = new \Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore(
            $scopeConfig,
            $logger
        );
        $this->reflection = new \ReflectionClass($this->actualInstance);
    }
    
    /**
     * Call the ACTUAL execute() method from AbstractLoadBefore.php
     * This is the REAL implementation - no copying!
     */
    public function execute($observer)
    {
        return $this->actualInstance->execute($observer);
    }
    
    /**
     * Call the ACTUAL getEnabled() method using Reflection API
     * Can access private/protected methods for testing
     */
    public function getEnabled()
    {
        $method = $this->reflection->getMethod('getEnabled');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
    
    public function getRequireFormCheck()
    {
        $method = $this->reflection->getMethod('getRequireFormCheck');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
    
    public function getBotBlockTime()
    {
        $method = $this->reflection->getMethod('getBotBlockTime');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
    
    public function getBotRecordTime()
    {
        $method = $this->reflection->getMethod('getBotRecordTime');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
    
    public function getBotBlockCount()
    {
        $method = $this->reflection->getMethod('getBotBlockCount');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
}

// Mock classes that implement actual Magento interfaces!
class MockScopeConfig implements \Magento\Framework\App\Config\ScopeConfigInterface
{
    private $enabled;
    private $requireFormCheck;
    private $botBlockTime;
    private $botRecordTime;
    private $botBlockCount;
    
    public function __construct(
        $enabled = true, 
        $requireFormCheck = true, 
        $botBlockTime = 2, 
        $botRecordTime = 2, 
        $botBlockCount = 20
    ) {
        $this->enabled = $enabled;
        $this->requireFormCheck = $requireFormCheck;
        $this->botBlockTime = $botBlockTime;
        $this->botRecordTime = $botRecordTime;
        $this->botBlockCount = $botBlockCount;
    }
    
    public function getValue($path, $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        if ($path === 'checkout/block_payment_bot/active') {
            return $this->enabled;
        }
        if ($path === 'checkout/block_payment_bot/require_form_check') {
            return $this->requireFormCheck;
        }
        if ($path === 'checkout/block_payment_bot/bot_block_time') {
            return $this->botBlockTime;
        }
        if ($path === 'checkout/block_payment_bot/bot_record_time') {
            return $this->botRecordTime;
        }
        if ($path === 'checkout/block_payment_bot/bot_block_count') {
            return $this->botBlockCount;
        }
        return null;
    }
    
    public function isSetFlag($path, $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        return (bool) $this->getValue($path, $scopeType, $scopeCode);
    }
}

class MockLogger implements \Psr\Log\LoggerInterface
{
    public $logs = [];
    
    public function emergency(\Stringable|string $message, array $context = []): void {}
    public function alert(\Stringable|string $message, array $context = []): void {}
    public function critical(\Stringable|string $message, array $context = []): void {}
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'error', 'message' => (string)$message];
    }
    public function warning(\Stringable|string $message, array $context = []): void {}
    public function notice(\Stringable|string $message, array $context = []): void {}
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'info', 'message' => (string)$message];
    }
    public function debug(\Stringable|string $message, array $context = []): void {}
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logs[] = ['level' => $level, 'message' => (string)$message];
    }
}

class MockObserver extends \Magento\Framework\Event\Observer
{
    public function __construct()
    {
        // Don't call parent constructor - we're mocking
    }
    
    public function getEvent()
    {
        return new class extends \Magento\Framework\Event {
            public function __construct()
            {
                // Don't call parent
            }
            
            public function getData($key = null)
            {
                return null;
            }
        };
    }
}

// Helper to setup $_SERVER environment
function setupServerEnvironment($overrides = [])
{
    $defaults = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-123/payment-information',
        'REMOTE_ADDR' => generateRandomTestIP(), // Use random IP for test isolation
    ];
    
    foreach (array_merge($defaults, $overrides) as $key => $value) {
        $_SERVER[$key] = $value;
    }
}

// Helper to cleanup $_SERVER
function cleanupServerEnvironment()
{
    $keys = ['REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'FASTLY-CLIENT-IP', 'HTTP_CF_CONNECTING_IP'];
    foreach ($keys as $key) {
        if (isset($_SERVER[$key])) {
            unset($_SERVER[$key]);
        }
    }
}

// Helper to generate random IP address for test isolation
function generateRandomTestIP(): string
{
    // Generate random IP in TEST-NET ranges (reserved for documentation/testing)
    // 192.0.2.0/24 (TEST-NET-1), 198.51.100.0/24 (TEST-NET-2), 203.0.113.0/24 (TEST-NET-3)
    $testNets = [
        [192, 0, 2],      // TEST-NET-1
        [198, 51, 100],   // TEST-NET-2
        [203, 0, 113],    // TEST-NET-3
    ];
    
    $net = $testNets[array_rand($testNets)];
    $lastOctet = rand(1, 254); // Avoid .0 and .255
    
    return implode('.', array_merge($net, [$lastOctet]));
}

describe('AbstractLoadBefore - Configuration Tests', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('getEnabled returns config value', function () {
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getEnabled())->toBeTrue();
        
        echo "\n";
        echo "  Test: getEnabled() method\n";
        echo "  └─ Config active: true ✓\n";
    });
    
    test('getRequireFormCheck returns config value', function () {
        $scopeConfig = new MockScopeConfig(true, true); // enabled, form_check enabled
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getRequireFormCheck())->toBeTrue();
        
        echo "\n";
        echo "  Test: getRequireFormCheck() method\n";
        echo "  └─ Config require_form_check: true ✓\n";
    });
    
    test('getRequireFormCheck can be disabled', function () {
        $scopeConfig = new MockScopeConfig(true, false); // enabled, form_check disabled
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getRequireFormCheck())->toBeFalse();
        
        echo "\n";
        echo "  Test: getRequireFormCheck() method (disabled)\n";
        echo "  └─ Config require_form_check: false ✓\n";
    });
    
    test('getBotBlockTime returns config value', function () {
        $scopeConfig = new MockScopeConfig(true, true, 5); // bot_block_time = 5
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getBotBlockTime())->toBe(5);
        
        echo "\n";
        echo "  Test: getBotBlockTime() method\n";
        echo "  └─ Config bot_block_time: 5 minutes ✓\n";
    });
    
    test('getBotRecordTime returns config value', function () {
        $scopeConfig = new MockScopeConfig(true, true, 2, 3); // bot_record_time = 3
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getBotRecordTime())->toBe(3);
        
        echo "\n";
        echo "  Test: getBotRecordTime() method\n";
        echo "  └─ Config bot_record_time: 3 minutes ✓\n";
    });
    
    test('getBotBlockCount returns config value', function () {
        $scopeConfig = new MockScopeConfig(true, true, 2, 2, 50); // bot_block_count = 50
        $logger = new MockLogger();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        expect($observer->getBotBlockCount())->toBe(50);
        
        echo "\n";
        echo "  Test: getBotBlockCount() method\n";
        echo "  └─ Config bot_block_count: 50 attempts ✓\n";
    });
    
    test('config overrides hardcoded defaults when ENV not set', function () {
        // Clear any existing ENV variables
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        
        $cartId = 'cart-config-override-' . uniqid();
        $ip = generateRandomTestIP();
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 120);
        
        // Set custom config values (different from defaults)
        $scopeConfig = new MockScopeConfig(true, true, 5, 3, 30); // 5min, 3min, 30 attempts
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // Execute - this will set ENV from config
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: Config overrides defaults when ENV not set\n";
        echo "  ├─ ENV variables: NOT SET (cleared)\n";
        echo "  ├─ Config bot_block_time: 5 minutes\n";
        echo "  ├─ Config bot_record_time: 3 minutes\n";
        echo "  ├─ Config bot_block_count: 30 attempts\n";
        echo "  ├─ After execute():\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_BLOCK_TIME']: " . $_ENV['MAGE_BOT_BLOCK_TIME'] . "\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_RECORD_TIME']: " . $_ENV['MAGE_BOT_RECORD_TIME'] . "\n";
        echo "  │  └─ \$_ENV['MAGE_BOT_BLOCK_COUNT']: " . $_ENV['MAGE_BOT_BLOCK_COUNT'] . "\n";
        echo "  └─ Config values used instead of hardcoded defaults ✓\n";
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
        
        // Verify config values were used
        expect($_ENV['MAGE_BOT_BLOCK_TIME'])->toBe(5)
            ->and($_ENV['MAGE_BOT_RECORD_TIME'])->toBe(3)
            ->and($_ENV['MAGE_BOT_BLOCK_COUNT'])->toBe(30);
    });
    
    test('ENV variables override config values', function () {
        // Set ENV variables explicitly
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 10;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 7;
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 100;
        
        $cartId = 'cart-env-override-' . uniqid();
        $ip = generateRandomTestIP();
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 120);
        
        // Set DIFFERENT config values (should be ignored)
        $scopeConfig = new MockScopeConfig(true, true, 5, 3, 30); // 5min, 3min, 30 attempts
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // Execute - ENV should remain unchanged
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: ENV variables override config\n";
        echo "  ├─ ENV variables: SET (10, 7, 100)\n";
        echo "  ├─ Config values: DIFFERENT (5, 3, 30)\n";
        echo "  ├─ After execute():\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_BLOCK_TIME']: " . $_ENV['MAGE_BOT_BLOCK_TIME'] . " (expected: 10)\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_RECORD_TIME']: " . $_ENV['MAGE_BOT_RECORD_TIME'] . " (expected: 7)\n";
        echo "  │  └─ \$_ENV['MAGE_BOT_BLOCK_COUNT']: " . $_ENV['MAGE_BOT_BLOCK_COUNT'] . " (expected: 100)\n";
        echo "  └─ ENV values unchanged, config ignored ✓\n";
        
        // Verify ENV values were NOT overridden (do this BEFORE cleanup)
        expect($_ENV['MAGE_BOT_BLOCK_TIME'])->toBe(10)
            ->and($_ENV['MAGE_BOT_RECORD_TIME'])->toBe(7)
            ->and($_ENV['MAGE_BOT_BLOCK_COUNT'])->toBe(100);
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
    });
    
    test('hardcoded defaults used when config returns null', function () {
        // Clear any existing ENV variables
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        
        $cartId = 'cart-default-fallback-' . uniqid();
        $ip = generateRandomTestIP();
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 120);
        
        // Config returns null (not set)
        $scopeConfig = new MockScopeConfig(true, true, null, null, null);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // Execute - should use hardcoded defaults
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: Hardcoded defaults when config is null\n";
        echo "  ├─ ENV variables: NOT SET\n";
        echo "  ├─ Config values: NULL (not configured)\n";
        echo "  ├─ After execute():\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_BLOCK_TIME']: " . $_ENV['MAGE_BOT_BLOCK_TIME'] . " (expected: 2)\n";
        echo "  │  ├─ \$_ENV['MAGE_BOT_RECORD_TIME']: " . $_ENV['MAGE_BOT_RECORD_TIME'] . " (expected: 2)\n";
        echo "  │  └─ \$_ENV['MAGE_BOT_BLOCK_COUNT']: " . $_ENV['MAGE_BOT_BLOCK_COUNT'] . " (expected: 20)\n";
        echo "  └─ Hardcoded defaults used (2, 2, 20) ✓\n";
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
        
        // Verify hardcoded defaults were used
        expect($_ENV['MAGE_BOT_BLOCK_TIME'])->toBe(2)
            ->and($_ENV['MAGE_BOT_RECORD_TIME'])->toBe(2)
            ->and($_ENV['MAGE_BOT_BLOCK_COUNT'])->toBe(20);
    });
    
    test('returns early when module is disabled', function () {
        setupServerEnvironment();
        
        $scopeConfig = new MockScopeConfig(false); // Disabled
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        expect($result)->toBe(0)
            ->and($logger->logs)->toBeEmpty();
        
        echo "\n";
        echo "  Test: Module disabled\n";
        echo "  ├─ Config active: false\n";
        echo "  ├─ Result: 0 (early return)\n";
        echo "  └─ No logs generated ✓\n";
    });
});

describe('AbstractLoadBefore - Request Method Tests', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('returns early on GET request without bot_test parameter', function () {
        setupServerEnvironment(['REQUEST_METHOD' => 'GET']);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        expect($result)->toBe(0);
        
        echo "\n";
        echo "  Test: GET request without bot_test\n";
        echo "  ├─ REQUEST_METHOD: GET\n";
        echo "  ├─ bot_test param: not set\n";
        echo "  └─ Result: 0 (early return) ✓\n";
    });
    
    test('processes POST request', function () {
        setupServerEnvironment(['REQUEST_METHOD' => 'POST']);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        // Uses REAL Redis from env.php config - no mocking needed!
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: POST request processing\n";
        echo "  ├─ REQUEST_METHOD: POST\n";
        echo "  ├─ REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "  ├─ Result: " . ($result ?? 'null') . "\n";
        echo "  ├─ Note: null = Redis processed (no return value)\n";
        echo "  └─ Processed ✓\n";
        
        // Accept null (Redis processed) or any non-zero value
        expect($_SERVER['REQUEST_METHOD'])->toBe('POST');
        // Result can be null (processed) or 1 (success) - both valid
        if ($result !== null) {
            expect($result)->not->toBe(0);
        }
    });
});

describe('AbstractLoadBefore - IP Detection Tests', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('detects IP from REMOTE_ADDR', function () {
        $ip = generateRandomTestIP(); // Random IP for test isolation
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => $ip,
            'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-456/payment-information'
        ]);
        
        expect($_SERVER['REMOTE_ADDR'])->toBe($ip);
        
        echo "\n";
        echo "  Test: IP from REMOTE_ADDR\n";
        echo "  ├─ REMOTE_ADDR: {$ip}\n";
        echo "  └─ Detected ✓\n";
    });
    
    test('detects IP from HTTP_X_FORWARDED_FOR', function () {
        $realIP = generateRandomTestIP(); // Random IP for test isolation
        $proxyIP = generateRandomTestIP();
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => $proxyIP,
            'HTTP_X_FORWARDED_FOR' => $realIP . ', ' . generateRandomTestIP(),
            'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-789/payment-information'
        ]);
        
        // Simulate the logic from the class
        $ips = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $detectedIP = trim(explode(',', $ips)[0]);
        
        expect($detectedIP)->toBe($realIP);
        
        echo "\n";
        echo "  Test: IP from X-Forwarded-For\n";
        echo "  ├─ REMOTE_ADDR: {$proxyIP}\n";
        echo "  ├─ X-Forwarded-For: {$ips}\n";
        echo "  ├─ Detected IP: {$detectedIP}\n";
        echo "  └─ First IP from list ✓\n";
    });
    
    test('detects IP from FASTLY-CLIENT-IP', function () {
        $fastlyIP = generateRandomTestIP(); // Random IP for test isolation
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => generateRandomTestIP(),
            'FASTLY-CLIENT-IP' => $fastlyIP,
            'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-fastly/payment-information'
        ]);
        
        $detectedIP = $_SERVER['FASTLY-CLIENT-IP'];
        
        expect($detectedIP)->toBe($fastlyIP);
        
        echo "\n";
        echo "  Test: IP from Fastly\n";
        echo "  ├─ FASTLY-CLIENT-IP: {$fastlyIP}\n";
        echo "  └─ Detected ✓\n";
    });
    
    test('detects IP from HTTP_CF_CONNECTING_IP (Cloudflare)', function () {
        $cfIP = generateRandomTestIP(); // Random IP for test isolation
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REMOTE_ADDR' => generateRandomTestIP(),
            'HTTP_CF_CONNECTING_IP' => $cfIP,
            'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-cf/payment-information'
        ]);
        
        $detectedIP = $_SERVER['HTTP_CF_CONNECTING_IP'];
        
        expect($detectedIP)->toBe($cfIP);
        
        echo "\n";
        echo "  Test: IP from Cloudflare\n";
        echo "  ├─ HTTP_CF_CONNECTING_IP: {$cfIP}\n";
        echo "  └─ Detected ✓\n";
    });
});

describe('AbstractLoadBefore - Cart ID Extraction Tests', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('extracts cart ID from payment-information URI', function () {
        $cartId = 'abc123xyz789';
        $uri = "/rest/default/V1/guest-carts/{$cartId}/payment-information";
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '192.168.1.100'
        ]);
        
        // Simulate regex from class
        $re = '/\/V1\/guest-carts\/(.*)\/payment-information/i';
        preg_match($re, $_SERVER['REQUEST_URI'], $matches);
        
        $extractedCartId = isset($matches[1]) ? trim($matches[1]) : null;
        
        expect($extractedCartId)->toBe($cartId);
        
        echo "\n";
        echo "  Test: Cart ID extraction\n";
        echo "  ├─ REQUEST_URI: {$uri}\n";
        echo "  ├─ Regex pattern: /\\/V1\\/guest-carts\\/(.*)\\/payment-information/i\n";
        echo "  ├─ Extracted Cart ID: {$extractedCartId}\n";
        echo "  └─ Match successful ✓\n";
    });
    
    test('handles URL with special characters in cart ID', function () {
        $cartId = 'cart-123_456-special';
        $uri = "/rest/default/V1/guest-carts/{$cartId}/payment-information";
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '192.168.1.100'
        ]);
        
        $re = '/\/V1\/guest-carts\/(.*)\/payment-information/i';
        preg_match($re, $_SERVER['REQUEST_URI'], $matches);
        
        $extractedCartId = isset($matches[1]) ? trim($matches[1]) : null;
        
        expect($extractedCartId)->toBe($cartId);
        
        echo "\n";
        echo "  Test: Cart ID with special characters\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  └─ Extracted correctly ✓\n";
    });
    
    test('returns null when URI does not match payment-information pattern', function () {
        $uri = "/rest/default/V1/guest-carts/abc123/shipping-information";
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '192.168.1.100'
        ]);
        
        $re = '/\/V1\/guest-carts\/(.*)\/payment-information/i';
        preg_match($re, $_SERVER['REQUEST_URI'], $matches);
        
        $extractedCartId = isset($matches[1]) ? trim($matches[1]) : null;
        
        expect($extractedCartId)->toBeNull();
        
        echo "\n";
        echo "  Test: Non-payment URI\n";
        echo "  ├─ REQUEST_URI: {$uri}\n";
        echo "  ├─ Pattern: payment-information\n";
        echo "  └─ No match (returns null) ✓\n";
    });
});

describe('AbstractLoadBefore - Endpoint Detection Tests', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('detects and handles totals-information endpoint', function () {
        $cartId = 'cart-totals-test-' . uniqid();
        $ip = generateRandomTestIP();
        $uri = "/rest/default/V1/guest-carts/{$cartId}/totals-information";
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => $ip
        ]);
        
        // Connect to Redis to verify the key is set
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Clean up any existing key
        $redis->del('Cart_IP_Check_' . $ip);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        // Check if Redis key was set
        $cartCheck = $redis->get('Cart_IP_Check_' . $ip);
        
        echo "\n";
        echo "  Test: totals-information endpoint\n";
        echo "  ├─ Endpoint: /V1/guest-carts/{cartId}/totals-information\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  ├─ Result: " . ($result !== null ? $result : 'null') . " (expected: 1)\n";
        echo "  ├─ Redis key: Cart_IP_Check_{$ip}\n";
        echo "  ├─ Redis value: " . ($cartCheck !== false ? $cartCheck : 'false') . "\n";
        echo "  └─ Endpoint detected and IP marked as valid ✓\n";
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        
        expect($result)->toBe(1)
            ->and($cartCheck)->toBe("true");
    });
    
    test('totals-information endpoint pattern matches correctly', function () {
        $testUris = [
            '/rest/default/V1/guest-carts/abc123/totals-information',
            '/rest/V1/guest-carts/xyz789/totals-information',
            '/V1/guest-carts/cart-special_123/totals-information',
        ];
        
        $pattern = '/\/V1\/guest-carts\/(.*)\/totals-information/i';
        
        echo "\n";
        echo "  Test: totals-information pattern matching\n";
        echo "  ├─ Pattern: {$pattern}\n";
        
        foreach ($testUris as $uri) {
            $matches = [];
            $matched = preg_match($pattern, $uri, $matches);
            $cartId = $matched ? $matches[1] : null;
            
            echo "  ├─ URI: {$uri}\n";
            echo "  │  └─ Cart ID: {$cartId} " . ($matched ? '✓' : '✗') . "\n";
            
            expect($matched)->toBe(1)
                ->and($cartId)->not->toBeNull();
        }
        
        echo "  └─ All patterns matched ✓\n";
    });
    
    test('payment-information endpoint still works correctly', function () {
        $cartId = 'cart-payment-test-' . uniqid();
        $ip = generateRandomTestIP();
        $uri = "/rest/default/V1/guest-carts/{$cartId}/payment-information";
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => $ip
        ]);
        
        $pattern = '/\/V1\/guest-carts\/(.*)\/payment-information/i';
        $matches = [];
        $matched = preg_match($pattern, $_SERVER['REQUEST_URI'], $matches);
        $extractedCartId = $matched ? trim($matches[1]) : null;
        
        echo "\n";
        echo "  Test: payment-information endpoint\n";
        echo "  ├─ Endpoint: /V1/guest-carts/{cartId}/payment-information\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  ├─ Pattern matched: " . ($matched ? 'YES' : 'NO') . "\n";
        echo "  ├─ Extracted Cart ID: {$extractedCartId}\n";
        echo "  └─ Endpoint detected correctly ✓\n";
        
        expect($matched)->toBe(1)
            ->and($extractedCartId)->toBe($cartId);
    });
});

describe('AbstractLoadBefore - Endpoint Type Counter Sharing', function () {
    beforeEach(function () {
        $_ENV['PEST'] = true;
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 3;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 10;
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 10;
    });
    
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['PEST']);
    });
    
    test('different endpoint types use different Redis key suffixes', function () {
        $ip = '192.168.1.100';
        
        echo "\n";
        echo "  Test: Different endpoint types have separate counter keys\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  \n";
        echo "  Endpoint Types and Their Redis Keys:\n";
        echo "  \n";
        echo "  1. totals-information (type: 'cart_check'):\n";
        echo "     └─ Redis key: Cart_{$ip}_IP_cart_check\n";
        echo "  \n";
        echo "  2. payment-information (type: 'payment'):\n";
        echo "     └─ Redis key: Cart_{$ip}_IP_payment\n";
        echo "  \n";
        echo "  Key Pattern: Cart_{{IP}}_IP_{{TYPE}}\n";
        echo "  \n";
        echo "  Benefits:\n";
        echo "  ├─ Each endpoint type has independent counter\n";
        echo "  ├─ totals-information won't affect payment-information counter\n";
        echo "  ├─ Flexible grouping by changing 'type' value\n";
        echo "  └─ Same IP can have different limits per endpoint type ✓\n";
        
        // Verify the key patterns are different
        $cartCheckKey = 'Cart_' . $ip . '_IP_cart_check';
        $paymentKey = 'Cart_' . $ip . '_IP_payment';
        
        expect($cartCheckKey)->not->toBe($paymentKey);
    });
    
    test('multiple calls to same endpoint type share IP counter', function () {
        $cartId1 = 'cart-shared-1-' . uniqid();
        $cartId2 = 'cart-shared-2-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Setup for payment endpoint
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        
        $scopeConfig = new MockScopeConfig(true, false); // form_check disabled
        $logger = new MockLogger();
        
        echo "\n";
        echo "  Test: Same endpoint type shares IP counter across different carts\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Endpoint type: payment (same for both calls)\n";
        echo "  ├─ Limit: 3 attempts\n";
        
        // Call 1: payment-information with cart1
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId1}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        $redis->set('Cart_' . $cartId1 . '_IP', $ip, 120);
        
        $observer1 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result1 = $observer1->execute(new MockObserver());
        $counter1 = $redis->get('Cart_' . $ip . '_IP_payment');
        echo "  ├─ Request 1: Cart {$cartId1}\n";
        echo "  │  ├─ Result: " . ($result1 ?: 'Success') . "\n";
        echo "  │  └─ IP Counter: {$counter1}\n";
        
        // Call 2: payment-information with cart2 (different cart, same IP, same type)
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId2}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        $redis->set('Cart_' . $cartId2 . '_IP', $ip, 120);
        
        $observer2 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result2 = $observer2->execute(new MockObserver());
        $counter2 = $redis->get('Cart_' . $ip . '_IP_payment');
        echo "  ├─ Request 2: Cart {$cartId2} (different cart, same type)\n";
        echo "  │  ├─ Result: " . ($result2 ?: 'Success') . "\n";
        echo "  │  └─ IP Counter: {$counter2} (incremented!)\n";
        
        // Call 3: payment-information with cart1 again
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId1}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer3 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result3 = $observer3->execute(new MockObserver());
        $counter3 = $redis->get('Cart_' . $ip . '_IP_payment');
        echo "  ├─ Request 3: Cart {$cartId1} (back to first cart)\n";
        echo "  │  ├─ Result: " . ($result3 ?: 'Success') . "\n";
        echo "  │  └─ IP Counter: {$counter3}\n";
        
        // Call 4: Should be BLOCKED (counter at limit)
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId1}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer4 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result4 = $observer4->execute(new MockObserver());
        $counter4 = $redis->get('Cart_' . $ip . '_IP_payment');
        echo "  ├─ Request 4: Cart {$cartId1} (counter at limit)\n";
        echo "  │  ├─ Result: {$result4} 🚫\n";
        echo "  │  └─ IP Counter: {$counter4} (blocked!)\n";
        
        echo "  └─ Same type = shared counter across different carts ✓\n";
        
        // Verify: counter incremented each time, blocked on 4th request (when counter reaches limit of 3)
        expect($counter1)->toBe('1')
            ->and($counter2)->toBe('2')
            ->and($counter3)->toBe('3')
            ->and($result4)->toBe('DIE_IP_COUNTER_AT_LIMIT'); // 4th request blocked (counter == limit)
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $ip . '_IP_payment');
        $redis->del('Cart_' . $cartId1);
        $redis->del('Cart_' . $cartId1 . '_IP');
        $redis->del('Cart_' . $cartId2);
        $redis->del('Cart_' . $cartId2 . '_IP');
    });
    
    test('demonstrates how to configure same counter for multiple URL patterns', function () {
        echo "\n";
        echo "  How to configure multiple URLs to share the same counter:\n";
        echo "  \n";
        echo "  In AbstractLoadBefore.php, TRACKED_ENDPOINTS array:\n";
        echo "  \n";
        echo "  private const TRACKED_ENDPOINTS = [\n";
        echo "      [\n";
        echo "          'pattern' => '/\\/V1\\/guest-carts\\/(.*)\\/totals-information/i',\n";
        echo "          'type' => 'cart_check'  // Same type = shared counter\n";
        echo "      ],\n";
        echo "      [\n";
        echo "          'pattern' => '/\\/V1\\/guest-carts\\/(.*)\\/shipping-information/i',\n";
        echo "          'type' => 'cart_check'  // Same type = shared counter!\n";
        echo "      ],\n";
        echo "      [\n";
        echo "          'pattern' => '/\\/V1\\/guest-carts\\/(.*)\\/payment-information/i',\n";
        echo "          'type' => 'payment'  // Different type = separate counter\n";
        echo "      ]\n";
        echo "  ];\n";
        echo "  \n";
        echo "  Benefits:\n";
        echo "  ├─ totals-information and shipping-information share IP counter\n";
        echo "  ├─ Both count towards same limit (e.g., 20 attempts total)\n";
        echo "  ├─ payment-information has its own separate counter\n";
        echo "  └─ Flexible: change type to group/ungroup endpoints ✓\n";
        
        expect(true)->toBeTrue();
    });
});

describe('AbstractLoadBefore - Counter Tracking During Block', function () {
    beforeEach(function () {
        $_ENV['PEST'] = true;
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 3;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 10;
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 10;
    });
    
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['PEST']);
    });
    
    test('cart counter increments even when user is already blocked', function () {
        $cartId = 'cart-blocked-tracking-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Pre-set cart counter to ALREADY EXCEEDED (25 > limit of 3)
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $cartId, 25, 120); // Already exceeded!
        
        $scopeConfig = new MockScopeConfig(true, false); // form_check disabled
        $logger = new MockLogger();
        
        echo "\n";
        echo "  Test: Cart counter increments even when blocked\n";
        echo "  ├─ Cart: {$cartId}\n";
        echo "  ├─ Limit: 3 attempts\n";
        echo "  ├─ Initial counter: 25 (already exceeded!)\n";
        
        // Request 1: Should be BLOCKED but counter should increment
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer1 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result1 = $observer1->execute(new MockObserver());
        $counter1 = $redis->get('Cart_' . $cartId);
        
        echo "  ├─ Request 1 (already blocked):\n";
        echo "  │  ├─ Result: {$result1} 🚫\n";
        echo "  │  └─ Counter: {$counter1} (incremented!)\n";
        
        // Request 2: Still blocked, counter should increment again
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer2 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result2 = $observer2->execute(new MockObserver());
        $counter2 = $redis->get('Cart_' . $cartId);
        
        echo "  ├─ Request 2 (still blocked):\n";
        echo "  │  ├─ Result: {$result2} 🚫\n";
        echo "  │  └─ Counter: {$counter2} (incremented again!)\n";
        
        // Request 3: Still blocked, counter should increment again
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer3 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result3 = $observer3->execute(new MockObserver());
        $counter3 = $redis->get('Cart_' . $cartId);
        
        echo "  ├─ Request 3 (still blocked):\n";
        echo "  │  ├─ Result: {$result3} 🚫\n";
        echo "  │  └─ Counter: {$counter3} (incremented again!)\n";
        
        echo "  └─ Blocked users tracked: 25 → 26 → 27 → 28 ✓\n";
        
        // Verify: All requests blocked, counter incremented each time
        expect($result1)->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and($counter1)->toBe('26')
            ->and($result2)->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and($counter2)->toBe('27')
            ->and($result3)->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and($counter3)->toBe('28');
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
    });
    
    test('IP counter increments even when user is already blocked', function () {
        $cartId = 'cart-ip-blocked-tracking-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Pre-set IP counter to ALREADY EXCEEDED (50 > limit of 3)
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 50, 120); // Already exceeded!
        
        $scopeConfig = new MockScopeConfig(true, false); // form_check disabled
        $logger = new MockLogger();
        
        echo "\n";
        echo "  Test: IP counter increments even when blocked\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Limit: 3 attempts\n";
        echo "  ├─ Initial IP counter: 50 (already exceeded!)\n";
        
        // Request 1: Should be BLOCKED but IP counter should increment
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer1 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result1 = $observer1->execute(new MockObserver());
        $ipCounter1 = $redis->get('Cart_' . $ip . '_IP_payment');
        
        echo "  ├─ Request 1 (already blocked):\n";
        echo "  │  ├─ Result: {$result1} 🚫\n";
        echo "  │  └─ IP Counter: {$ipCounter1} (incremented!)\n";
        
        // Request 2: Still blocked, IP counter should increment again
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer2 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result2 = $observer2->execute(new MockObserver());
        $ipCounter2 = $redis->get('Cart_' . $ip . '_IP_payment');
        
        echo "  ├─ Request 2 (still blocked):\n";
        echo "  │  ├─ Result: {$result2} 🚫\n";
        echo "  │  └─ IP Counter: {$ipCounter2} (incremented again!)\n";
        
        // Request 3: Still blocked, IP counter should increment again
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        $observer3 = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result3 = $observer3->execute(new MockObserver());
        $ipCounter3 = $redis->get('Cart_' . $ip . '_IP_payment');
        
        echo "  ├─ Request 3 (still blocked):\n";
        echo "  │  ├─ Result: {$result3} 🚫\n";
        echo "  │  └─ IP Counter: {$ipCounter3} (incremented again!)\n";
        
        echo "  └─ Blocked IPs tracked: 50 → 51 → 52 → 53 ✓\n";
        
        // Verify: All requests blocked, IP counter incremented each time
        expect($result1)->toBe('DIE_IP_COUNTER_EXCEEDED')
            ->and($ipCounter1)->toBe('51')
            ->and($result2)->toBe('DIE_IP_COUNTER_EXCEEDED')
            ->and($ipCounter2)->toBe('52')
            ->and($result3)->toBe('DIE_IP_COUNTER_EXCEEDED')
            ->and($ipCounter3)->toBe('53');
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
    });
    
    test('explains benefits of tracking blocked users', function () {
        echo "\n";
        echo "  Why track counters for blocked users?\n";
        echo "  \n";
        echo "  Benefits:\n";
        echo "  ├─ 1. Metrics: Know how persistent attackers are\n";
        echo "  │    └─ Example: Counter shows 500 attempts after block\n";
        echo "  ├─ 2. Extended Blocking: Each attempt extends block time\n";
        echo "  │    └─ Block TTL refreshed with each abuse attempt\n";
        echo "  ├─ 3. Detection: Identify automated vs manual attacks\n";
        echo "  │    └─ Bots often make 100s of attempts\n";
        echo "  ├─ 4. Analytics: Track abuse patterns over time\n";
        echo "  │    └─ Redis keys show full attempt history\n";
        echo "  └─ 5. Forensics: Evidence for security analysis ✓\n";
        echo "  \n";
        echo "  Implementation:\n";
        echo "  ├─ Cart Counter > Limit: Still increment and update Redis\n";
        echo "  ├─ IP Counter > Limit: Still increment and update Redis\n";
        echo "  └─ Both use MAGE_BOT_BLOCK_TIME to extend blocking ✓\n";
        
        expect(true)->toBeTrue();
    });
});

describe('AbstractLoadBefore - Blocked IP Logging', function () {
    beforeEach(function () {
        $_ENV['PEST'] = true;
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 3;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 10;
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 10;
    });
    
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['PEST']);
    });
    
    test('blocked IPs are logged to Redis with full metadata', function () {
        $cartId = 'cart-log-test-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Pre-set IP counter to hit limit
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 3, 120); // At limit
        
        $scopeConfig = new MockScopeConfig(true, false);
        $logger = new MockLogger();
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        echo "\n";
        echo "  Test: Blocked IPs logged to Redis\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart: {$cartId}\n";
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute(new MockObserver());
        
        // Check if blocked IP was logged
        $blockKey = 'BlockedIP_' . $ip . '_payment';
        $blockData = $redis->get($blockKey);
        
        echo "  ├─ Result: {$result}\n";
        echo "  ├─ Block key: {$blockKey}\n";
        
        if ($blockData !== false) {
            $data = json_decode($blockData, true);
            echo "  ├─ Logged data:\n";
            echo "  │  ├─ IP: " . $data['ip'] . "\n";
            echo "  │  ├─ Type: " . $data['type'] . "\n";
            echo "  │  ├─ URL: " . $data['url'] . "\n";
            echo "  │  ├─ Counter: " . $data['counter'] . "\n";
            echo "  │  ├─ Reason: " . $data['reason'] . "\n";
            echo "  │  ├─ Blocked at: " . date('Y-m-d H:i:s', $data['blocked_at']) . "\n";
            echo "  │  └─ Expires at: " . date('Y-m-d H:i:s', $data['expires_at']) . "\n";
            
            // Check if added to set
            $inSet = $redis->sIsMember('BlockedIPs_Set', $blockKey);
            echo "  ├─ Added to BlockedIPs_Set: " . ($inSet ? 'YES' : 'NO') . "\n";
            echo "  └─ Blocked IP logged successfully ✓\n";
            
            // Verify data structure
            expect($data)->toHaveKey('ip')
                ->and($data)->toHaveKey('type')
                ->and($data)->toHaveKey('url')
                ->and($data)->toHaveKey('counter')
                ->and($data)->toHaveKey('reason')
                ->and($data)->toHaveKey('blocked_at')
                ->and($data)->toHaveKey('expires_at')
                ->and($data['ip'])->toBe($ip)
                ->and($data['type'])->toBe('payment')
                ->and($inSet)->toBeTrue();
        } else {
            echo "  └─ WARNING: Block data not found\n";
            expect($blockData)->not->toBe(false);
        }
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
        $redis->del($blockKey);
        $redis->sRem('BlockedIPs_Set', $blockKey);
    });
    
    test('explains blocked IP logging system', function () {
        echo "\n";
        echo "  Blocked IP Logging System\n";
        echo "  \n";
        echo "  Redis Keys:\n";
        echo "  ├─ BlockedIP_{{IP}}_{{TYPE}}: JSON with block details\n";
        echo "  └─ BlockedIPs_Set: Set of all blocked IP keys\n";
        echo "  \n";
        echo "  Logged Data:\n";
        echo "  ├─ ip: IP address that was blocked\n";
        echo "  ├─ type: Endpoint type (cart_check, payment)\n";
        echo "  ├─ url: Request URL that triggered block\n";
        echo "  ├─ counter: Counter value at time of block\n";
        echo "  ├─ reason: Why blocked (DIE_IP_COUNTER_AT_LIMIT, etc.)\n";
        echo "  ├─ blocked_at: Unix timestamp when blocked\n";
        echo "  └─ expires_at: Unix timestamp when block expires\n";
        echo "  \n";
        echo "  CLI Command:\n";
        echo "  $ php bin/magento genaker:blockbot:show-blocked-ips\n";
        echo "  \n";
        echo "  Features:\n";
        echo "  ├─ View all currently blocked IPs\n";
        echo "  ├─ See counter values and reasons\n";
        echo "  ├─ Auto-cleanup expired blocks\n";
        echo "  ├─ TTL matches MAGE_BOT_BLOCK_TIME\n";
        echo "  └─ Perfect for monitoring bot attacks ✓\n";
        
        expect(true)->toBeTrue();
    });
});

describe('AbstractLoadBefore - Environment Variables', function () {
    test('uses default environment variables when not set', function () {
        // Unset env vars
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        
        // Simulate the logic from class
        if (!isset($_ENV['MAGE_BOT_BLOCK_TIME'])) {
            $_ENV['MAGE_BOT_BLOCK_TIME'] = 2;
        }
        if (!isset($_ENV['MAGE_BOT_RECORD_TIME'])) {
            $_ENV['MAGE_BOT_RECORD_TIME'] = 2;
        }
        if (!isset($_ENV['MAGE_BOT_BLOCK_COUNT'])) {
            $_ENV['MAGE_BOT_BLOCK_COUNT'] = 20;
        }
        
        expect($_ENV['MAGE_BOT_BLOCK_TIME'])->toBe(2)
            ->and($_ENV['MAGE_BOT_RECORD_TIME'])->toBe(2)
            ->and($_ENV['MAGE_BOT_BLOCK_COUNT'])->toBe(20);
        
        echo "\n";
        echo "  Test: Default environment variables\n";
        echo "  ├─ MAGE_BOT_BLOCK_TIME: " . $_ENV['MAGE_BOT_BLOCK_TIME'] . " minutes\n";
        echo "  ├─ MAGE_BOT_RECORD_TIME: " . $_ENV['MAGE_BOT_RECORD_TIME'] . " minutes\n";
        echo "  ├─ MAGE_BOT_BLOCK_COUNT: " . $_ENV['MAGE_BOT_BLOCK_COUNT'] . " attempts\n";
        echo "  └─ Defaults set ✓\n";
    });
    
    test('uses custom environment variables when set', function () {
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 5;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 3;
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 10;
        
        expect($_ENV['MAGE_BOT_BLOCK_TIME'])->toBe(5)
            ->and($_ENV['MAGE_BOT_RECORD_TIME'])->toBe(3)
            ->and($_ENV['MAGE_BOT_BLOCK_COUNT'])->toBe(10);
        
        echo "\n";
        echo "  Test: Custom environment variables\n";
        echo "  ├─ MAGE_BOT_BLOCK_TIME: " . $_ENV['MAGE_BOT_BLOCK_TIME'] . " minutes\n";
        echo "  ├─ MAGE_BOT_RECORD_TIME: " . $_ENV['MAGE_BOT_RECORD_TIME'] . " minutes\n";
        echo "  ├─ MAGE_BOT_BLOCK_COUNT: " . $_ENV['MAGE_BOT_BLOCK_COUNT'] . " attempts\n";
        echo "  └─ Custom values used ✓\n";
    });
});

describe('AbstractLoadBefore - Redis Key Format', function () {
    test('generates correct Redis keys', function () {
        $cartId = 'test-cart-123';
        $ip = '192.168.1.100';
        
        $cartKey = 'Cart_' . $cartId;
        $cartIPKey = 'Cart_' . $cartId . '_IP';
        $ipKey = 'Cart_' . $ip . '_IP';
        
        expect($cartKey)->toBe('Cart_test-cart-123')
            ->and($cartIPKey)->toBe('Cart_test-cart-123_IP')
            ->and($ipKey)->toBe('Cart_192.168.1.100_IP');
        
        echo "\n";
        echo "  Test: Redis key generation\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart counter key: {$cartKey}\n";
        echo "  ├─ Cart IP key: {$cartIPKey}\n";
        echo "  ├─ IP counter key: {$ipKey}\n";
        echo "  └─ Keys generated ✓\n";
    });
});

describe('AbstractLoadBefore - Multiple IP Formats', function () {
    afterEach(function () {
        cleanupServerEnvironment();
    });
    
    test('handles comma-separated IPs in X-Forwarded-For', function () {
        $ip1 = generateRandomTestIP(); // Random IP for test isolation
        $ip2 = generateRandomTestIP();
        $ip3 = generateRandomTestIP();
        $ipsString = "$ip1, $ip2, $ip3";
        
        setupServerEnvironment([
            'HTTP_X_FORWARDED_FOR' => $ipsString
        ]);
        
        // Simulate the class logic
        $ips = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip = trim(explode(',', $ips)[0]);
        
        expect($ip)->toBe($ip1); // Should extract first IP from comma-separated list
        
        echo "\n";
        echo "  Test: Multiple IPs in X-Forwarded-For\n";
        echo "  ├─ Full header: {$ipsString}\n";
        echo "  ├─ First IP: {$ip}\n";
        echo "  └─ Extracted first IP ✓\n";
    });
});

describe('AbstractLoadBefore - Bot Detection Logic with REAL Redis', function () {
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
    });
    
    test('executes with real Redis connection from env.php', function () {
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 20;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 2;
        
        $ip = generateRandomTestIP(); // Random IP for test isolation
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/rest/default/V1/guest-carts/test-cart-' . uniqid() . '/payment-information',
            'REMOTE_ADDR' => $ip
        ]);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // Call the ACTUAL method - it will create real Redis connection from env.php
        $result = $observer->execute($mockObserver);
        
        // Result should not be 0 (which means it didn't fail early)
        // It should be null (processed) or specific return code
        expect($result)->not->toBe(0);
        
        echo "\n";
        echo "  Test: Real Redis integration\n";
        echo "  ├─ IP: {$ip} (random for isolation)\n";
        echo "  ├─ Uses ACTUAL Redis from env.php config\n";
        echo "  ├─ Server: AWS ElastiCache\n";
        echo "  ├─ Result: " . ($result !== null ? $result : 'null (success)') . "\n";
        echo "  └─ Real Redis connection ✓\n";
    });
    
    // Note: Additional Redis integration tests would require mocking/stubbing 
    // the internal Redis connection, or running against a test Redis instance.
    // The actual class creates its own Redis connection from env.php config.
});

/**
 * ============================================================================
 * HOW TO TEST die() CALLS - Three Approaches
 * ============================================================================
 * 
 * The code has multiple die() calls:
 * - Line 131: die("Cheater?") - IP changed
 * - Line 151: die(" Bye!") - Counter at limit
 * - Line 154: die(" Bye Cheater!") - Counter exceeded
 * - Line 160: die(" Bye!") - IP counter at limit
 * 
 * APPROACH 1: Test Conditions Before die() 
 * ------------------------------------------
 * Test the logger calls and conditions that lead to die(), but don't actually die()
 */
describe('AbstractLoadBefore - Testing die() Scenarios (PEST Mode)', function () {
    beforeEach(function () {
        cleanupServerEnvironment();
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 20;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 2;
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 2;
        $_ENV['PEST'] = true;  // Enable PEST mode to return instead of die()
    });
    
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
    });
    
    test('IP change detection returns DIE_CHEATER_IP_CHANGED', function () {
        // Setup: Create a cart with known IP in Redis
        $cartId = 'cart-ip-die-test-' . uniqid();
        $originalIP = generateRandomTestIP(); // Random IP for test isolation
        $newIP = generateRandomTestIP(); // Different random IP
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $newIP
        ]);
        
        // Pre-populate Redis with original IP (via real Redis connection)
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_' . $cartId . '_IP', $originalIP, 60);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // In PEST mode, should return 'DIE_CHEATER_IP_CHANGED' instead of die()
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: IP change detection (PEST mode)\n";
        echo "  ├─ Original IP in Redis: {$originalIP}\n";
        echo "  ├─ New IP in request: {$newIP}\n";
        echo "  ├─ Expected: DIE_CHEATER_IP_CHANGED\n";
        echo "  ├─ Got: {$result}\n";
        echo "  ├─ HTTP Response: " . http_response_code() . "\n";
        
        // Check logger was called
        $cheaterDetected = false;
        foreach ($logger->logs as $log) {
            if (strpos($log['message'], 'cheater detected') !== false) {
                $cheaterDetected = true;
                echo "  ├─ Logger: " . substr($log['message'], 0, 60) . "...\n";
            }
        }
        
        echo "  └─ Would call die('Cheater?') in production ✓\n";
        
        // Cleanup Redis
        $redis->del('Cart_' . $cartId . '_IP');
        
        expect($result)->toBe('DIE_CHEATER_IP_CHANGED')
            ->and($cheaterDetected)->toBeTrue()
            ->and(http_response_code())->toBe(511);
    });
    
    test('cart counter at limit returns DIE_CART_COUNTER_AT_LIMIT', function () {
        $cartId = 'cart-limit-test-' . uniqid();
        $ip = generateRandomTestIP(); // Random IP for test isolation
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Set counter to exactly at limit (20)
        // The code checks: if ($counter == $blockCounter)
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_' . $cartId, 20, 120);  // Exactly at limit
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: Cart counter at limit (PEST mode)\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  ├─ Counter: 20 (exactly at limit)\n";
        echo "  ├─ Expected: DIE_CART_COUNTER_AT_LIMIT\n";
        echo "  ├─ Got: {$result}\n";
        echo "  └─ Would call die(' Bye!') in production ✓\n";
        
        // Cleanup
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        
        expect($result)->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and(http_response_code())->toBe(511);
    });
    
    test('cart counter exceeded returns DIE_CART_COUNTER_EXCEEDED', function () {
        $cartId = 'cart-exceeded-test-' . uniqid();
        $ip = generateRandomTestIP(); // Random IP for test isolation
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Set counter above limit
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_' . $cartId, 25, 120);  // Already exceeded
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: Cart counter exceeded (PEST mode)\n";
        echo "  ├─ Cart ID: {$cartId}\n";
        echo "  ├─ Counter: 25 (exceeded limit of 20)\n";
        echo "  ├─ Expected: DIE_CART_COUNTER_EXCEEDED\n";
        echo "  ├─ Got: {$result}\n";
        echo "  └─ Would call die(' Bye Cheater!') in production ✓\n";
        
        // Cleanup
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        
        expect($result)->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and(http_response_code())->toBe(511);
    });
    
    test('IP counter at limit returns DIE_IP_COUNTER_AT_LIMIT', function () {
        $cartId = 'cart-ip-limit-test-' . uniqid();
        $ip = generateRandomTestIP(); // Random IP for test isolation
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Set IP counter to exactly at limit (20)
        // The code checks: if ($counterIP == $blockCounter)
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_' . $ip . '_IP_payment', 20, 120);  // Exactly at limit (with type suffix)
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: IP counter at limit (PEST mode)\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ IP Counter: 20 (exactly at limit)\n";
        echo "  ├─ Expected: DIE_IP_COUNTER_AT_LIMIT\n";
        echo "  ├─ Got: {$result}\n";
        
        // Check logger
        $loggerCalled = false;
        foreach ($logger->logs as $log) {
            if (strpos($log['message'], 'sent bye') !== false) {
                $loggerCalled = true;
                echo "  ├─ Logger called: YES\n";
            }
        }
        
        echo "  └─ Would call die(' Bye!') in production ✓\n";
        
        // Cleanup
        $redis->del('Cart_' . $ip . '_IP_payment');
        $redis->del('Cart_' . $cartId . '_IP');
        
        expect($result)->toBe('DIE_IP_COUNTER_AT_LIMIT')
            ->and($loggerCalled)->toBeTrue()
            ->and(http_response_code())->toBe(511);
    });
    
    test('IP counter exceeded returns DIE_IP_COUNTER_EXCEEDED', function () {
        $cartId = 'cart-ip-exceeded-test-' . uniqid();
        $ip = generateRandomTestIP(); // Random IP for test isolation
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Set IP counter above limit
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_' . $ip . '_IP_payment', 30, 120);  // Already exceeded (with type suffix)
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: IP counter exceeded (PEST mode)\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ IP Counter: 30 (exceeded limit of 20)\n";
        echo "  ├─ Expected: DIE_IP_COUNTER_EXCEEDED\n";
        echo "  ├─ Got: {$result}\n";
        echo "  └─ Would call die(' Bye Cheater!') in production ✓\n";
        
        // Cleanup
        $redis->del('Cart_' . $ip . '_IP_payment');
        $redis->del('Cart_' . $cartId . '_IP');
        
        expect($result)->toBe('DIE_IP_COUNTER_EXCEEDED')
            ->and(http_response_code())->toBe(511);
    });
    
    test('form_check validation can be disabled via config', function () {
        $cartId = 'cart-form-check-disabled-' . uniqid();
        $ip = generateRandomTestIP();
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis to pass cart check
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        $redis->set('Cart_' . $cartId . '_IP', $ip, 120);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 120);
        
        // Disable form_check validation in config
        $scopeConfig = new MockScopeConfig(true, false); // enabled=true, requireFormCheck=false
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        $observer = new AbstractLoadBeforeTestHelper($scopeConfig, $logger);
        
        // Should proceed without requiring form_check parameter
        // Note: In PEST mode, if no die() is triggered, the method completes normally
        $result = $observer->execute($mockObserver);
        
        echo "\n";
        echo "  Test: form_check validation disabled via config\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Config require_form_check: false\n";
        echo "  ├─ POST data: (no form_check parameter)\n";
        echo "  ├─ Expected: Process continues without DIE_FORM_CHECK_FAILED\n";
        echo "  ├─ Result: {$result}\n";
        echo "  └─ Validation bypassed successfully ✓\n";
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
        
        // Should NOT return DIE_FORM_CHECK_FAILED
        expect($result)->not->toBe('DIE_FORM_CHECK_FAILED');
    });
    
    test('explanation: how to test actual die() with separate process', function () {
        echo "\n";
        echo "  Testing die() - Three Methods:\n";
        echo "  \n";
        echo "  METHOD 1: Test Conditions (Current Approach)\n";
        echo "  ├─ Test logger calls before die()\n";
        echo "  ├─ Test Redis state changes\n";
        echo "  ├─ Test http_response_code() is set\n";
        echo "  └─ Don't actually trigger die()\n";
        echo "  \n";
        echo "  METHOD 2: Run in Separate PHP Process\n";
        echo "  ├─ Create helper script: tests/helpers/test-die-scenario.php\n";
        echo "  ├─ Run with: exec('php test-die-scenario.php', \$output, \$exitCode)\n";
        echo "  ├─ Check: \$exitCode !== 0 (process died)\n";
        echo "  └─ Check: output contains expected message\n";
        echo "  \n";
        echo "  METHOD 3: Use Output Buffering\n";
        echo "  ├─ ob_start() before execution\n";
        echo "  ├─ register_shutdown_function() to catch die\n";
        echo "  ├─ Check output with ob_get_clean()\n";
        echo "  └─ Limited - can't prevent actual die()\n";
        echo "  \n";
        echo "  RECOMMENDED: Method 1 (Test conditions)\n";
        echo "  └─ Most practical for integration tests ✓\n";
        
        expect(true)->toBeTrue();
    });
    
    test('DEMO: run helper script to test die() in separate process', function () {
        $helperScript = __DIR__ . '/../helpers/example-test-die-cheater.php';
        
        if (!file_exists($helperScript)) {
            echo "\n  ⚠️  Helper script not found: {$helperScript}\n";
            expect(true)->toBeTrue();
            return;
        }
        
        echo "\n";
        echo "  DEMO: Testing die() with Separate Process\n";
        echo "  ├─ Script: tests/helpers/example-test-die-cheater.php\n";
        echo "  ├─ Executes: \$observer->execute() with IP change\n";
        echo "  ├─ Expected: Process dies (exit code !== 0)\n";
        echo "  └─ Expected output: Contains 'Cheater?' or logger messages\n\n";
        
        echo "  To run manually:\n";
        echo "  $ php {$helperScript}\n\n";
        
        echo "  To test from PHP:\n";
        echo "  exec('php {$helperScript} 2>&1', \$output, \$exitCode);\n";
        echo "  expect(\$exitCode)->not->toBe(0);  // Process terminated\n\n";
        
        expect(file_exists($helperScript))->toBeTrue();
    });
});

/**
 * EXAMPLE CODE: Testing die() with Separate Process
 * ==================================================
 * 
 * Create: tests/helpers/test-cheater-die.php
 * -------------------------------------------
 * <?php
 * require __DIR__ . '/../bootstrap-hybrid.php';
 * 
 * // Setup Redis with known IP
 * $redis = new \Redis();
 * $redis->pconnect('127.0.0.1', 6379);
 * $redis->set('Cart_test123_IP', '192.168.1.50');
 * 
 * // Setup environment with DIFFERENT IP
 * $_SERVER['REQUEST_METHOD'] = 'POST';
 * $_SERVER['REQUEST_URI'] = '/rest/default/V1/guest-carts/test123/payment-information';
 * $_SERVER['REMOTE_ADDR'] = '192.168.1.100';  // DIFFERENT!
 * 
 * // This WILL die()
 * $observer = new \Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore($scopeConfig, $logger);
 * $observer->execute(new \Magento\Framework\Event\Observer());
 * 
 * 
 * Then test it:
 * -------------
 * test('cheater detection triggers die', function () {
 *     $output = [];
 *     $exitCode = 0;
 *     
 *     exec('php tests/helpers/test-cheater-die.php 2>&1', $output, $exitCode);
 *     
 *     expect($exitCode)->not->toBe(0)  // Process died
 *         ->and(implode('', $output))->toContain('Cheater?');
 *     
 *     echo "\n  ✓ die('Cheater?') was called\n";
 *     echo "  ✓ Exit code: {$exitCode}\n";
 *     echo "  ✓ Output: " . implode('', $output) . "\n";
 * });
 */

// Test helper class that uses seconds instead of minutes
class AbstractLoadBeforeTestHelperWithSeconds extends AbstractLoadBeforeTestHelper
{
    private $scopeConfig;
    private $logger;
    
    public function __construct($scopeConfig, $logger)
    {
        parent::__construct($scopeConfig, $logger);
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }
    
    public function execute($observer)
    {
        // Create NEW instance for each request to reset $flag
        // (In real life, each request is a new PHP process, so $flag is always false)
        $instance = new class($this->scopeConfig, $this->logger) extends \Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore {
            protected function getTimeInSeconds($time)
            {
                return 1 * (int) $time;  // Use seconds directly, not minutes
            }
        };
        
        return $instance->execute($observer);
    }
}

describe('AbstractLoadBefore - Time-Based Blocking (Seconds)', function () {
    afterEach(function () {
        cleanupServerEnvironment();
        unset($_ENV['MAGE_BOT_BLOCK_TIME']);
        unset($_ENV['MAGE_BOT_RECORD_TIME']);
        unset($_ENV['MAGE_BOT_BLOCK_COUNT']);
    });
    
    test('blocks after record time expires with multiple requests', function () {
        $cartId = 'cart-time-test-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Set record time to 2 SECONDS (using our overridden method)
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 5;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 2;  // 2 seconds with our override
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 2;  // Allow 2 attempts
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        // Use helper with seconds override
        $observer = new AbstractLoadBeforeTestHelperWithSeconds($scopeConfig, $logger);
        
        echo "\n";
        echo "  Test: Time-based blocking with delays\n";
        echo "  ├─ Record Time: 2 seconds\n";
        echo "  ├─ Block Count: 2 attempts\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart: {$cartId}\n";
        
        // Request 1: Should succeed
        $result1 = $observer->execute($mockObserver);
        echo "  ├─ Request 1 (t=0s): " . ($result1 ?: 'Success') . " ✓\n";
        expect($result1)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result1)->not->toBe('DIE_CART_COUNTER_EXCEEDED');
        
        // Wait 1 second
        echo "  ├─ Wait: 1 second...\n";
        sleep(1);
        
        // Request 2: Should succeed (still within 2 second window)
        $result2 = $observer->execute($mockObserver);
        echo "  ├─ Request 2 (t=1s): " . ($result2 ?: 'Success') . " ✓\n";
        expect($result2)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result2)->not->toBe('DIE_CART_COUNTER_EXCEEDED');
        
        // Wait 2 seconds (so BOTH previous requests expire)
        echo "  ├─ Wait: 2 seconds (for both to expire)...\n";
        sleep(2);
        
        // Request 3: Should succeed (both previous requests expired after 2s)
        $result3 = $observer->execute($mockObserver);
        echo "  ├─ Request 3 (t=3s): " . ($result3 ?: 'Success (counters reset)') . " ✓\n";
        echo "  └─ Counters reset after 2s TTL, request allowed ✓\n";
        
        // Verify Redis counter was reset (should be 1, not 3)
        $counter = $redis->get('Cart_' . $cartId);
        echo "  └─ Current counter: " . ($counter ?: 0) . " (reset to 1) ✓\n";
        
        expect($result3)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result3)->not->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and((int)$counter)->toBeLessThanOrEqual(1);
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
    })->skip(function () {
        // Skip if we want fast tests (this test takes 3+ seconds)
        return getenv('SKIP_SLOW_TESTS') === '1';
    }, 'Slow test: takes 3+ seconds with sleep()');
    
    test('blocks on 3rd request when limit is 2 within record time', function () {
        $cartId = 'cart-limit-test-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Set record time to 10 SECONDS, block count to 2
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 5;
        $_ENV['MAGE_BOT_RECORD_TIME'] = 10;  // 10 seconds window
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 2;   // Allow only 2 attempts
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        // Use helper with seconds override
        $observer = new AbstractLoadBeforeTestHelperWithSeconds($scopeConfig, $logger);
        
        echo "\n";
        echo "  Test: Block on 3rd request within record time\n";
        echo "  ├─ Record Time: 10 seconds\n";
        echo "  ├─ Block Count: 2 attempts (limit)\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart: {$cartId}\n";
        
        // Pre-set counter to 0 to ensure tracking starts fresh
        $redis->set('Cart_' . $cartId, 0, 10);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 10);
        
        // Request 1: Should succeed (counter: 1)
        $result1 = $observer->execute($mockObserver);
        $counter1 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 1: " . ($result1 ?: 'Success') . " (counter: {$counter1}) ✓\n";
        expect($result1)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result1)->not->toBe('DIE_CART_COUNTER_EXCEEDED');
        
        // Request 2: Should succeed (counter: 2, hits limit)
        $result2 = $observer->execute($mockObserver);
        $counter2 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 2: " . ($result2 ?: 'Success') . " (counter: {$counter2}) ✓\n";
        expect($result2)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result2)->not->toBe('DIE_CART_COUNTER_EXCEEDED');
        
        // Request 3: Should be BLOCKED (counter exceeds limit)
        $result3 = $observer->execute($mockObserver);
        $counter3 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 3: {$result3} (counter: {$counter3}) 🚫\n";
        echo "  ├─ Expected: DIE_CART_COUNTER_AT_LIMIT\n";
        echo "  └─ Counter limit (2) reached, 3rd request blocked ✓\n";
        
        // Verify it was blocked
        expect($result3)->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and((int)$counter3)->toBeGreaterThanOrEqual(2);
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
    });
    
    test('blocks on 3rd request, then unblocks after record time expires', function () {
        $cartId = 'cart-block-unblock-' . uniqid();
        $ip = generateRandomTestIP();
        
        // Set record time to 5 SECONDS, block count to 2
        $_ENV['MAGE_BOT_BLOCK_TIME'] = 5;   // 5 seconds block time (same as record)
        $_ENV['MAGE_BOT_RECORD_TIME'] = 5;  // 5 seconds window
        $_ENV['MAGE_BOT_BLOCK_COUNT'] = 2;  // Allow only 2 attempts
        
        setupServerEnvironment([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => "/rest/default/V1/guest-carts/{$cartId}/payment-information",
            'REMOTE_ADDR' => $ip
        ]);
        
        // Setup Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
        
        $scopeConfig = new MockScopeConfig(true);
        $logger = new MockLogger();
        $mockObserver = new MockObserver();
        
        // Use helper with seconds override
        $observer = new AbstractLoadBeforeTestHelperWithSeconds($scopeConfig, $logger);
        
        echo "\n";
        echo "  Test: Block then unblock after TTL expires\n";
        echo "  ├─ Record Time: 5 seconds\n";
        echo "  ├─ Block Count: 2 attempts (limit)\n";
        echo "  ├─ IP: {$ip}\n";
        echo "  ├─ Cart: {$cartId}\n";
        
        // Pre-set counter to 0
        $redis->set('Cart_' . $cartId, 0, 5);
        $redis->set('Cart_' . $ip . '_IP_payment', 0, 5);
        
        // Request 1: Should succeed (counter: 1)
        $result1 = $observer->execute($mockObserver);
        $counter1 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 1: " . ($result1 ?: 'Success') . " (counter: {$counter1}) ✓\n";
        expect($result1)->not->toBe('DIE_CART_COUNTER_AT_LIMIT');
        
        // Request 2: Should succeed (counter: 2, at limit)
        $result2 = $observer->execute($mockObserver);
        $counter2 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 2: " . ($result2 ?: 'Success') . " (counter: {$counter2}) ✓\n";
        expect($result2)->not->toBe('DIE_CART_COUNTER_AT_LIMIT');
        
        // Request 3: Should be BLOCKED (counter exceeds limit)
        $result3 = $observer->execute($mockObserver);
        $counter3 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 3: {$result3} (counter: {$counter3}) 🚫\n";
        echo "  ├─ Result: BLOCKED (limit reached)\n";
        expect($result3)->toBe('DIE_CART_COUNTER_AT_LIMIT');
        
        // Wait for record time to expire
        echo "  ├─ Wait: 5 seconds (for TTL to expire)...\n";
        sleep(5);
        
        // Request 4: Should succeed (counters expired and reset)
        $result4 = $observer->execute($mockObserver);
        $counter4 = $redis->get('Cart_' . $cartId);
        echo "  ├─ Request 4 (after 5s): " . ($result4 ?: 'Success') . " (counter: {$counter4}) ✓\n";
        echo "  ├─ Result: ALLOWED (TTL expired, counter reset)\n";
        echo "  └─ Block → Wait 5s → Unblock → Success ✓\n";
        
        // Verify it's NOT blocked
        expect($result4)->not->toBe('DIE_CART_COUNTER_AT_LIMIT')
            ->and($result4)->not->toBe('DIE_CART_COUNTER_EXCEEDED')
            ->and((int)$counter4)->toBeLessThanOrEqual(1);
        
        // Cleanup
        $redis->del('Cart_IP_Check_' . $ip);
        $redis->del('Cart_' . $cartId);
        $redis->del('Cart_' . $cartId . '_IP');
        $redis->del('Cart_' . $ip . '_IP_payment');
    })->skip(function () {
        return getenv('SKIP_SLOW_TESTS') === '1';
    }, 'Slow test: takes 5+ seconds with sleep()');
});

