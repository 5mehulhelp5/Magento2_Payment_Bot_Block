<?php
/**
 * Copyright © Genaker. All rights reserved.
 */
declare(strict_types=1);

namespace Genaker\BlockPaymentBot\Model;

/**
 * Provides blocked IPs data from Redis
 */
class BlockedIpsProvider
{
    private ?\Redis $redis = null;

    /**
     * Get active blocked IPs with their data
     *
     * @return array{blocks: array, config: array, redis_connected: bool}
     */
    public function getBlockedIps(): array
    {
        $redis = $this->getRedisConnection();
        if ($redis === null) {
            return [
                'blocks' => [],
                'config' => $this->getConfig(),
                'redis_connected' => false
            ];
        }

        $currentTime = time();

        // Remove expired entries, then fetch only active ones
        $redis->zRemRangeByScore('BlockedIPs_Set', '-inf', (string) $currentTime);
        $blockedKeys = $redis->zRangeByScore('BlockedIPs_Set', (string) $currentTime, '+inf');
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

            $blockData['expires_in'] = ($blockData['expires_at'] ?? 0) - $currentTime;
            $blockData['endpoint'] = $this->extractEndpoint($blockData['url'] ?? '');
            $activeBlocks[] = $blockData;
        }

        usort($activeBlocks, fn($a, $b) => ($b['blocked_at'] ?? 0) <=> ($a['blocked_at'] ?? 0));

        return [
            'blocks' => $activeBlocks,
            'config' => $this->getConfig(),
            'redis_connected' => true
        ];
    }

    private function getConfig(): array
    {
        return [
            'block_limit' => $_ENV['MAGE_BOT_BLOCK_COUNT'] ?? 20,
            'block_time' => $_ENV['MAGE_BOT_BLOCK_TIME'] ?? 2,
            'record_time' => $_ENV['MAGE_BOT_RECORD_TIME'] ?? 2,
        ];
    }

    private function extractEndpoint(string $url): string
    {
        if (preg_match('#/V1/[^/]+/([^?]+)#', $url, $m)) {
            return $m[1];
        }
        return $url ?: '-';
    }

    private function getRedisConnection(): ?\Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        try {
            $config = require BP . '/app/etc/env.php';
            $backendOpts = $config['cache']['frontend']['default']['backend_options'] ?? [];
            $redisConfig = $backendOpts['remote_backend_options'] ?? $backendOpts;

            if (!$redisConfig || !isset($redisConfig['server'], $redisConfig['port'])) {
                return null;
            }

            $this->redis = new \Redis();
            $this->redis->pconnect($redisConfig['server'], (int) $redisConfig['port']);
            if (isset($redisConfig['database'])) {
                $this->redis->select((int) $redisConfig['database']);
            }
            if (!empty($redisConfig['password'])) {
                $this->redis->auth($redisConfig['password']);
            }
            return $this->redis;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
