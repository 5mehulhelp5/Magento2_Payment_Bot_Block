<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare (strict_types = 1);

namespace Genaker\BlockPaymentBot\Observer\Webapi\Core;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class AbstractLoadBefore implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * Tracked endpoint patterns
     * 
     * Each endpoint configuration:
     * - pattern: Regex pattern to match the endpoint
     * - type: 'cart_check' (marks IP as valid) or 'payment' (validates and tracks)
     *  if we need the same counter use the same type for url 
     */
    private const TRACKED_ENDPOINTS = [
        [
            'pattern' => '/\/V1\/guest-carts\/([^\/]+)\/totals-information/i',
            'type' => 'cart_check'
        ],
        [
            'pattern' => '/\/V1\/guest-carts\/([^\/]+)\/payment-information/i',
            'type' => 'payment'
        ]
    ];

    // Execute only once per request ...
    protected $flag = false;

    protected $scopeConfig;

    protected $logger;

    /**
     * Cached Redis connection instance
     * Reused within the same PHP process (PHP-FPM worker)
     * @var \Redis|null
     */
    private $redis = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function getEnabled()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/active', ScopeInterface::SCOPE_STORE);
    }

    public function getRequireFormCheck()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/require_form_check', ScopeInterface::SCOPE_STORE);
    }

    public function getBotBlockTime()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/bot_block_time', ScopeInterface::SCOPE_STORE);
    }

    public function getBotRecordTime()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/bot_record_time', ScopeInterface::SCOPE_STORE);
    }

    public function getBotBlockCount()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/bot_block_count', ScopeInterface::SCOPE_STORE);
    }

    public function getBehindProxy()
    {
        return $this->scopeConfig->getValue('checkout/block_payment_bot/behind_proxy', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get client IP.
     * When "Behind Trusted Proxy" is enabled: reads from proxy headers.
     * When disabled: uses REMOTE_ADDR only (proxy headers ignored).
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->getBehindProxy()) {
            $proxyHeaders = ['HTTP_X_FORWARDED_FOR', 'FASTLY-CLIENT-IP', 'HTTP_CF_CONNECTING_IP'];
            foreach ($proxyHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    $parts = explode(',', (string) $_SERVER[$header]);
                    $candidate = trim($parts[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Get whitelisted IPs - these are never blocked
     *
     * @return string[]
     */
    private function getIpWhitelist(): array
    {
        $value = $this->scopeConfig->getValue('checkout/block_payment_bot/ip_whitelist', ScopeInterface::SCOPE_STORE);
        if (empty($value)) {
            return [];
        }
        $decoded = is_string($value) ? @unserialize($value) : $value;
        if (empty($decoded) || !is_array($decoded)) {
            return [];
        }
        $ips = [];
        foreach ($decoded as $row) {
            $ip = trim($row['ip'] ?? '');
            if ($ip !== '') {
                $ips[] = $ip;
            }
        }
        return $ips;
    }

    /**
     * Default rules when no rules set in admin (from TRACKED_ENDPOINTS)
     */
    private const DEFAULT_RULES = [
        ['path' => '/\/V1\/guest-carts\/([^\/]+)\/totals-information/i', 'block_count' => 20, 'block_time' => 2],
        ['path' => '/\/V1\/guest-carts\/([^\/]+)\/payment-information/i', 'block_count' => 20, 'block_time' => 2],
    ];

    /**
     * Get matching bot rule for request URI, or null to use defaults
     * Uses admin bot_rules when set, otherwise default rules from config/constant
     *
     * @param string $requestUri
     * @return array|null Rule with block_count, block_time
     */
    private function getMatchingRule(string $requestUri): ?array
    {
        $rules = $this->scopeConfig->getValue('checkout/block_payment_bot/bot_rules', ScopeInterface::SCOPE_STORE);
        $decoded = null;
        if (!empty($rules)) {
            $decoded = is_string($rules) ? @unserialize($rules) : $rules;
        }
        if (empty($decoded) || !is_array($decoded)) {
            $decoded = self::DEFAULT_RULES;
        }
        $defaultBlockCount = (int) ($this->getBotBlockCount() ?: 20);
        $defaultBlockTime = (int) ($this->getBotBlockTime() ?: 2);

        foreach ($decoded as $rule) {
            $path = $rule['path'] ?? '';
            if ($path !== '' && @preg_match($path, $requestUri)) {
                $blockCount = $rule['block_count'] ?? '';
                $blockTime = $rule['block_time'] ?? '';
                return [
                    'block_count' => (int) ($blockCount !== '' ? $blockCount : $defaultBlockCount),
                    'block_time' => (int) ($blockTime !== '' ? $blockTime : $defaultBlockTime),
                ];
            }
        }
        return null;
    }

    /**
     * Convert time to seconds
     * Production: minutes to seconds (60x)
     * Tests: override to use 1x for faster testing
     * 
     * @param int $time Time value
     * @return int Time in seconds
     */
    protected function getTimeInSeconds($time)
    {
        return 60 * (int) $time;
    }

    /**
     * Get Redis connection with configuration from env.php
     * 
     * Reuses cached connection within the same PHP process (PHP-FPM worker)
     * Uses pconnect() for persistent connections across requests
     *
     * @return \Redis|null Returns Redis instance or null if connection cannot be established
     */
    private function getRedisConnection()
    {
        // Return cached connection if available and still alive
        if ($this->redis !== null) {
            try {
                // Verify connection is still alive
                if ($this->redis->ping()) {
                    return $this->redis;
                }
            } catch (\Exception $e) {
                // Connection lost, will create new one below
                $this->redis = null;
            }
        }

        $config = require BP . '/app/etc/env.php';

        // Get Redis configuration from default cache
        $redisConfig = null;
        
        // Check for RemoteSynchronizedCache (remote_backend_options)
        if (isset($config['cache']['frontend']['default']['backend_options']['remote_backend_options'])) {
            $redisConfig = $config['cache']['frontend']['default']['backend_options']['remote_backend_options'];
        }
        // Check for direct Redis backend (backend_options)
        elseif (isset($config['cache']['frontend']['default']['backend_options'])) {
            $backendOptions = $config['cache']['frontend']['default']['backend_options'];
            if (isset($backendOptions['server']) && isset($backendOptions['port'])) {
                $redisConfig = $backendOptions;
            }
        }
        
        // No valid Redis configuration found
        if ($redisConfig === null || !isset($redisConfig['server']) || !isset($redisConfig['port'])) {
            return null;
        }

        $redis = new \Redis();
        
        $persistentIdentifier = $redisConfig['persistent_identifier'] ?? 'blockbot';

        try {
            $redis->pconnect(
                $redisConfig['server'],
                (int) $redisConfig['port'],
                0, // timeout
                $persistentIdentifier
            );
            
            // Select database if specified
            if (isset($redisConfig['database'])) {
                $redis->select((int) $redisConfig['database']);
            }
            
            // Authenticate if password is provided
            if (!empty($redisConfig['password'])) {
                $redis->auth($redisConfig['password']);
            }
            
            // Cache the connection for reuse
            $this->redis = $redis;
        } catch (\Exception $e) {
            return null;
        }

        return $this->redis;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $allowBotTest = isset($_GET['bot_test']) && (isset($_ENV['PEST']) && $_ENV['PEST'] === true);
        if (($_SERVER['REQUEST_METHOD'] !== 'POST' && !$allowBotTest) || $this->flag === true) {
            return 0;
        }

        // We are usin native Redis we are not using Magento Broken Framework
        // if you don't have native redis instaleed this extension will not work
        if (!class_exists('\Redis')) {
            return 0;
        }

        if (!$this->getEnabled()) {
            return 0;
        }

        $this->flag = true;

        try {
            $matches = [];
            $endpointConfig = null;

            // Check which endpoint pattern matches the current request
            foreach (self::TRACKED_ENDPOINTS as $config) {
                if (preg_match($config['pattern'], $_SERVER['REQUEST_URI'], $matches, PREG_OFFSET_CAPTURE)) {
                    $endpointConfig = $config;
                    break;
                }
            }

            // Process if an endpoint was matched
            if ($endpointConfig !== null) {
                $ip = $this->getClientIp();

                if (in_array($ip, $this->getIpWhitelist(), true)) {
                    return 0;
                }

                // Validate form_check parameter for payment requests (skip in PEST mode, configurable via admin)
                if ($endpointConfig['type'] !== 'cart_check' 
                    && $this->getRequireFormCheck() 
                    && !(isset($_ENV['PEST']) && $_ENV['PEST'] === true)) {
                    $rawData = file_get_contents("php://input");
                    $data = json_decode($rawData, true);

                    $formCheck = false;
                    
                    if (isset($data["paymentMethod"]) &&
                        isset($data["paymentMethod"]["additional_data"]) &&
                        isset($data["paymentMethod"]["additional_data"]["form_check"]) &&
                        $data["paymentMethod"]["additional_data"]["form_check"] === "true") {
                        $formCheck = true;
                    }
                    
                    if (!$formCheck) {
                        $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore cheater detected $ip - checkout form error");
                        
                        // Get Redis connection for logging
                        $redis = $this->getRedisConnection();
                        if ($redis !== null) {
                            $this->logBlockedIP($redis, $ip, 'payment', $_SERVER['REQUEST_URI'], 0, 'DIE_FORM_CHECK_FAILED');
                        }
                        
                        http_response_code(402);
                        return $this->die('DIE_FORM_CHECK_FAILED', "Credit Card Error");
                    }
                }

                // ENV takes precedence, then matching bot rule, then default fields
                $rule = $this->getMatchingRule($_SERVER['REQUEST_URI']);
                if (!isset($_ENV['MAGE_BOT_BLOCK_TIME'])) {
                    $_ENV['MAGE_BOT_BLOCK_TIME'] = $rule !== null ? $rule['block_time'] : ($this->getBotBlockTime() ?: 2);
                }
                if (!isset($_ENV['MAGE_BOT_RECORD_TIME'])) {
                    $_ENV['MAGE_BOT_RECORD_TIME'] = $rule !== null ? $rule['block_time'] : ($this->getBotRecordTime() ?: 2);
                }
                if (!isset($_ENV['MAGE_BOT_BLOCK_COUNT'])) {
                    $_ENV['MAGE_BOT_BLOCK_COUNT'] = $rule !== null ? $rule['block_count'] : ($this->getBotBlockCount() ?: 20);
                }

                // Get Redis connection
                $redis = $this->getRedisConnection();
                if ($redis === null) {
                    return 0;
                }

                // Handle cart_check endpoint - mark IP as valid
                if ($endpointConfig['type'] === 'cart_check') {
                    $redis->set('Cart_IP_Check_' . $ip, "true", 3600);
                    return 1;
                }

                // Handle payment endpoint - verify cart was accessed (skip in PEST mode)
                if ($endpointConfig['type'] === 'payment') {
                    if (!(isset($_ENV['PEST']) && $_ENV['PEST'] === true)) {
                        $cartCheck = $redis->get('Cart_IP_Check_' . $ip);

                        if ($cartCheck === false) {
                            $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore cheater detected $ip - checkout without cart");
                            
                            // Log this incident
                            $this->logBlockedIP($redis, $ip, $endpointConfig['type'], $_SERVER['REQUEST_URI'], 0, 'DIE_NO_CART_CHECK');
                            
                            http_response_code(401);
                            return $this->die('DIE_NO_CART_CHECK', "Credit Card Error");
                        }
                    }

                    // Get customer Cart Id
                    $cartId = trim($matches[1][0]);

                    if (empty($cartId) || empty($ip)) {
                        $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore observer logical error: ip: " . $ip . ",  or cartId: " . $cartId . " are empty");
                        return 0;
                    }

                    $this->logger->debug("Genaker_BlockPaymentBot::AbstractLoadBefore observer Begin track: ip: " . $ip . ", cartId: " . $cartId);

                    $result = $this->checkAndUpdateCounters($redis, $cartId, $ip, $endpointConfig['type']);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore observer error: " . $e->getMessage());
        }
    }

    /**
     * Set IP counter in Redis
     *
     * @param \Redis $redis Redis connection
     * @param string $ip IP address
     * @param string $type Endpoint type (cart_check or payment)
     * @param int $counterIP Counter value to set
     * @param string $timeEnvKey ENV key for time (MAGE_BOT_BLOCK_TIME or MAGE_BOT_RECORD_TIME)
     * @return void
     */
    private function setCounter($redis, string $ip, string $type, int $counterIP, string $timeEnvKey)
    {
        $redis->set('Cart_' . $ip . '_IP_' . $type, $counterIP, $this->getTimeInSeconds($_ENV[$timeEnvKey]));
    }

    /**
     * Log blocked IP to Redis for tracking and CLI display
     *
     * @param \Redis $redis Redis connection
     * @param string $ip IP address
     * @param string $type Endpoint type
     * @param string $url Request URL
     * @param int $counter Current counter value
     * @param string $reason Block reason (DIE_IP_COUNTER_AT_LIMIT, etc.)
     * @return void
     */
    private function logBlockedIP($redis, string $ip, string $type, string $url, int $counter, string $reason)
    {
        $path = strpos($url, '?') !== false ? substr($url, 0, strpos($url, '?')) : $url;
        $blockKey = 'BlockedIP_' . $ip . '_' . $type;
        $blockTtl = $this->getTimeInSeconds($_ENV['MAGE_BOT_BLOCK_TIME']);
        $expiresAt = time() + $blockTtl;
        $blockData = [
            'ip' => $ip,
            'type' => $type,
            'url' => $path,
            'counter' => $counter,
            'limit' => $_ENV['MAGE_BOT_BLOCK_COUNT'] ?? 20,
            'reason' => $reason,
            'blocked_at' => time(),
            'expires_at' => $expiresAt
        ];

        $redis->setex($blockKey, $blockTtl, json_encode($blockData));

        // Sorted set: score = expiry timestamp, auto-prunes expired on each write
        $redis->zRemRangeByScore('BlockedIPs_Set', '-inf', (string) time());
        $redis->zAdd('BlockedIPs_Set', $expiresAt, $blockKey);
    }

    /**
     * Check and update cart and IP counters
     *
     * @param \Redis $redis Redis connection
     * @param string $cartId Cart ID
     * @param string $ip IP address
     * @param string $type Endpoint type (cart_check or payment)
     * @return string|null Returns die value if blocked, null if allowed to continue
     */
    private function checkAndUpdateCounters($redis, string $cartId, string $ip, string $type)
    {
        //counter of the same cart invocation without or with different ip
        $counter = $redis->get('Cart_' . $cartId);
        $counterIP = $redis->get('Cart_' . $ip . '_IP_' . $type);
        $previousIP = $redis->get('Cart_' . $cartId . '_IP');

        // If the cheater changed IP address we are blocking that guy right away
        if ($previousIP !== $ip && $previousIP !== false) {
            $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore cheater detected, ip: " . $ip . ", previousIP: " . $previousIP . ", cartId: " . $cartId);
            $this->logBlockedIP($redis, $ip, $type, $_SERVER['REQUEST_URI'], $counterIP, 'DIE_CHEATER_IP_CHANGED');
            http_response_code(511);
            return $this->die('DIE_CHEATER_IP_CHANGED', "Cheater?");
        }

        if ($counter === false) {
            $counter = 0;
        }
        if ($counterIP === false) {
            $counterIP = 0;
        }
        
        // Ensure values are integers to prevent boolean increment warnings
        $counter = (int) $counter;
        $counterIP = (int) $counterIP;

        $blockCounter = (int) $_ENV['MAGE_BOT_BLOCK_COUNT'];

        if ($counter == $blockCounter) {
            $redis->set('Cart_' . $cartId, ++$counter, $this->getTimeInSeconds($_ENV['MAGE_BOT_BLOCK_TIME']));
            $redis->set('Cart_' . $cartId . '_IP', $ip, $this->getTimeInSeconds($_ENV['MAGE_BOT_BLOCK_TIME']));
            $this->logBlockedIP($redis, $ip, $type, $_SERVER['REQUEST_URI'], $counter, 'DIE_CART_COUNTER_AT_LIMIT');
            http_response_code(511);
            return $this->die('DIE_CART_COUNTER_AT_LIMIT', " Bye!");
        } else if ($counter > $blockCounter) {
            // Track continued abuse attempts even when already blocked
            $redis->set('Cart_' . $cartId, ++$counter, $this->getTimeInSeconds($_ENV['MAGE_BOT_BLOCK_TIME']));
            $this->logBlockedIP($redis, $ip, $type, $_SERVER['REQUEST_URI'], $counter, 'DIE_CART_COUNTER_EXCEEDED');
            http_response_code(511);
            return $this->die('DIE_CART_COUNTER_EXCEEDED', " Bye Cheater!");
        }
        
        if ($counterIP == $blockCounter) {
            $this->logger->error("Genaker_BlockPaymentBot::AbstractLoadBefore sent bye, ip: " . $ip . ", cartId: " . $cartId);
            $this->setCounter($redis, $ip, $type, ++$counterIP, 'MAGE_BOT_BLOCK_TIME');
            $this->logBlockedIP($redis, $ip, $type, $_SERVER['REQUEST_URI'], $counterIP, 'DIE_IP_COUNTER_AT_LIMIT');
            http_response_code(511);
            return $this->die('DIE_IP_COUNTER_AT_LIMIT', " Bye!");
        } else if ($counterIP > $blockCounter) {
            // Track continued abuse attempts even when already blocked
            $this->setCounter($redis, $ip, $type, ++$counterIP, 'MAGE_BOT_BLOCK_TIME');
            $this->logBlockedIP($redis, $ip, $type, $_SERVER['REQUEST_URI'], $counterIP, 'DIE_IP_COUNTER_EXCEEDED');
            http_response_code(511);
            return $this->die('DIE_IP_COUNTER_EXCEEDED', " Bye Cheater!");
        }

        $redis->set('Cart_' . $cartId, ++$counter, $this->getTimeInSeconds($_ENV['MAGE_BOT_RECORD_TIME']));
        $redis->set('Cart_' . $cartId . '_IP', $ip, $this->getTimeInSeconds($_ENV['MAGE_BOT_RECORD_TIME']));
        $this->setCounter($redis, $ip, $type, ++$counterIP, 'MAGE_BOT_RECORD_TIME');

        return null;
    }

    /**
     * Die with message or return test value in PEST mode
     *
     * @param string $testReturnValue Value to return when testing (PEST mode)
     * @param string $message Message to die with in production
     * @return string|void Returns test value in PEST mode, dies in production
     */
    private function die(string $testReturnValue, string $message = '')
    {
        // In PEST test mode, return the test value instead of dying
        if (isset($_ENV['PEST']) && $_ENV['PEST'] === true) {
            return $testReturnValue;
        }
        
        // Production: die with message
        die($message);
    }
}
