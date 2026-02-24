<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ShowBlockedIPs extends Command
{
    /**
     * @var \Redis|null
     */
    private $redis = null;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('genaker:blockbot:show-blocked-ips');
        $this->setDescription('Show all currently blocked IPs and clean up expired blocks');
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Genaker BlockPaymentBot - Blocked IPs Report</info>');
        $output->writeln('<info>=============================================</info>');
        $output->writeln('');
        
        // Display current configuration
        $botBlockCount = $_ENV['MAGE_BOT_BLOCK_COUNT'] ?? 20;
        $botBlockTime = $_ENV['MAGE_BOT_BLOCK_TIME'] ?? 2;
        $botRecordTime = $_ENV['MAGE_BOT_RECORD_TIME'] ?? 2;
        
        $output->writeln('<comment>Configuration:</comment>');
        $output->writeln('  Block Limit: ' . $botBlockCount . ' attempts');
        $output->writeln('  Block Duration: ' . $botBlockTime . ' minutes');
        $output->writeln('  Tracking Window: ' . $botRecordTime . ' minutes');
        $output->writeln('');

        // Get Redis connection
        $redis = $this->getRedisConnection();
        if ($redis === null) {
            $output->writeln('<error>Failed to connect to Redis</error>');
            $output->writeln('<comment>Uses default cache Redis configuration from app/etc/env.php</comment>');
            $output->writeln('');
            
            try {
                $config = require BP . '/app/etc/env.php';
                $redisConfig = null;
                $configPath = '';
                
                // Check RemoteSynchronizedCache first
                if (isset($config['cache']['frontend']['default']['backend_options']['remote_backend_options'])) {
                    $redisConfig = $config['cache']['frontend']['default']['backend_options']['remote_backend_options'];
                    $configPath = 'cache.frontend.default.backend_options.remote_backend_options';
                }
                // Check direct Redis backend
                elseif (isset($config['cache']['frontend']['default']['backend_options'])) {
                    $backendOptions = $config['cache']['frontend']['default']['backend_options'];
                    if (isset($backendOptions['server']) && isset($backendOptions['port'])) {
                        $redisConfig = $backendOptions;
                        $configPath = 'cache.frontend.default.backend_options';
                    }
                }
                
                if ($redisConfig) {
                    $output->writeln('<comment>Found Redis configuration at: ' . $configPath . '</comment>');
                    $output->writeln('  Server: ' . ($redisConfig['server'] ?? 'not set'));
                    $output->writeln('  Port: ' . ($redisConfig['port'] ?? 'not set'));
                    $output->writeln('  Database: ' . ($redisConfig['database'] ?? '0'));
                    $output->writeln('');
                    $output->writeln('<comment>Please check if Redis is running on the configured host/port</comment>');
                } else {
                    $output->writeln('<error>Redis configuration not found in app/etc/env.php</error>');
                    $output->writeln('<comment>Checked locations:</comment>');
                    $output->writeln('  - cache.frontend.default.backend_options.remote_backend_options');
                    $output->writeln('  - cache.frontend.default.backend_options');
                }
            } catch (\Exception $e) {
                $output->writeln('  <error>Error reading configuration: ' . $e->getMessage() . '</error>');
            }
            
            return Command::FAILURE;
        }

        $currentTime = time();

        // Prune expired entries, then fetch only active ones
        $redis->zRemRangeByScore('BlockedIPs_Set', '-inf', (string) $currentTime);
        $blockedKeys = $redis->zRangeByScore('BlockedIPs_Set', (string) $currentTime, '+inf');

        if (empty($blockedKeys)) {
            $output->writeln('<comment>No blocked IPs found.</comment>');
            return Command::SUCCESS;
        }

        $activeBlocks = [];

        foreach ($blockedKeys as $key) {
            $data = $redis->get($key);

            if ($data === false) {
                $redis->zRem('BlockedIPs_Set', $key);
                continue;
            }

            $blockData = json_decode($data, true);
            if ($blockData === null) {
                continue;
            }

            $activeBlocks[] = $blockData;
        }

        // Display active blocks
        if (!empty($activeBlocks)) {
            $output->writeln('<info>Active Blocked IPs (' . count($activeBlocks) . '):</info>');
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(['IP Address', 'Type', 'URL', 'Current', 'Limit', 'Reason', 'Blocked At', 'Expires In']);

            foreach ($activeBlocks as $block) {
                $blockedAt = date('Y-m-d H:i:s', $block['blocked_at']);
                $expiresIn = $this->formatTimeRemaining($block['expires_at'] - $currentTime);
                
                $table->addRow([
                    $block['ip'],
                    $block['type'],
                    $this->truncateUrl($block['url']),
                    $block['counter'] ?? 'N/A',
                    $block['limit'] ?? 'N/A',
                    $this->formatReason($block['reason']),
                    $blockedAt,
                    $expiresIn
                ]);
            }

            $table->render();
            $output->writeln('');
        }

        // Display cleanup summary
        if (!empty($expiredBlocks)) {
            $output->writeln('<comment>Cleaned up ' . count($expiredBlocks) . ' expired block(s)</comment>');
        }

        $output->writeln('');
        $output->writeln('<info>Total Active Blocks: ' . count($activeBlocks) . '</info>');
        $output->writeln('<info>Total Cleaned Up: ' . count($expiredBlocks) . '</info>');

        return Command::SUCCESS;
    }

    /**
     * Get or create Redis connection
     *
     * @return \Redis|null
     */
    private function getRedisConnection()
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        try {
            // Load Redis configuration from env.php
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
            
            $this->redis = new \Redis();
            $connected = $this->redis->pconnect(
                $redisConfig['server'],
                (int) $redisConfig['port']
            );

            if (!$connected) {
                $this->redis = null;
                return null;
            }
            
            // Select database if specified
            if (isset($redisConfig['database'])) {
                $this->redis->select((int) $redisConfig['database']);
            }
            
            // Authenticate if password is provided
            if (!empty($redisConfig['password'])) {
                $this->redis->auth($redisConfig['password']);
            }

            return $this->redis;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format time remaining in human-readable format
     *
     * @param int $seconds
     * @return string
     */
    private function formatTimeRemaining(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' sec';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' min';
        } else {
            return round($seconds / 3600, 1) . ' hrs';
        }
    }

    /**
     * Format block reason for display
     *
     * @param string $reason
     * @return string
     */
    private function formatReason(string $reason): string
    {
        $reasons = [
            'DIE_IP_COUNTER_AT_LIMIT' => 'IP at limit',
            'DIE_IP_COUNTER_EXCEEDED' => 'IP exceeded',
            'DIE_CART_COUNTER_AT_LIMIT' => 'Cart at limit',
            'DIE_CART_COUNTER_EXCEEDED' => 'Cart exceeded',
            'DIE_CHEATER_IP_CHANGED' => 'IP changed',
            'DIE_FORM_CHECK_FAILED' => 'Form check failed',
            'DIE_NO_CART_CHECK' => 'No cart check'
        ];

        return $reasons[$reason] ?? $reason;
    }

    /**
     * Truncate URL for display
     *
     * @param string $url
     * @return string
     */
    private function truncateUrl(string $url): string
    {
        if (strlen($url) > 50) {
            return substr($url, 0, 47) . '...';
        }
        return $url;
    }
}

