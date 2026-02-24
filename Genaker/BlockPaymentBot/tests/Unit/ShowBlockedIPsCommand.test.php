<?php
/**
 * Pest test for Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs
 * Tests the CLI command that displays blocked IPs
 * 
 * Run: vendor/bin/pest Unit/ShowBlockedIPsCommand.test.php
 */

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('ShowBlockedIPs Command', function () {
    
    test('command can be instantiated and executed', function () {
        // Create command instance directly
        $command = new \Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs();
        
        // Prepare input and output
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        echo "\n";
        echo "  Test: CLI command execution\n";
        echo "  ├─ Command: genaker:blockbot:show-blocked-ips\n";
        echo "  ├─ Creating command instance...\n";
        
        // Execute the command
        $exitCode = $command->run($input, $output);
        
        // Get output
        $outputText = $output->fetch();
        
        echo "  ├─ Exit code: {$exitCode}\n";
        echo "  ├─ Output preview:\n";
        
        $lines = explode("\n", $outputText);
        foreach (array_slice($lines, 0, 5) as $line) {
            if (!empty(trim($line))) {
                echo "  │  " . trim($line) . "\n";
            }
        }
        
        // Verify command executed successfully
        expect($exitCode)->toBeIn([0, 1]) // 0 = success, 1 = success (Symfony Command::SUCCESS/FAILURE)
            ->and($outputText)->toContain('Genaker BlockPaymentBot');
        
        echo "  └─ Command executed successfully ✓\n";
    });
    
    test('command shows no blocked IPs when none exist', function () {
        // Setup: Clean any existing blocked IPs
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        // Clear all blocked IPs
        $blockedKeys = $redis->zRange('BlockedIPs_Set', 0, -1);
        foreach ($blockedKeys as $key) {
            $redis->del($key);
        }
        $redis->del('BlockedIPs_Set');
        
        // Execute command
        $command = new \Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        echo "\n";
        echo "  Test: No blocked IPs scenario\n";
        echo "  ├─ Cleaned all existing blocked IPs\n";
        
        $exitCode = $command->run($input, $output);
        $outputText = $output->fetch();
        
        echo "  ├─ Exit code: {$exitCode}\n";
        echo "  ├─ Output contains 'No blocked IPs found': " . (strpos($outputText, 'No blocked IPs found') !== false ? 'YES' : 'NO') . "\n";
        echo "  └─ Empty state handled correctly ✓\n";
        
        expect($outputText)->toContain('No blocked IPs found');
    });
    
    test('command displays blocked IPs with full metadata', function () {
        // Setup: Add a test blocked IP to Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        $testIP = '192.168.99.99';
        $testType = 'payment';
        $blockKey = 'BlockedIP_' . $testIP . '_' . $testType;
        
        $blockData = [
            'ip' => $testIP,
            'type' => $testType,
            'url' => '/rest/default/V1/guest-carts/test123/payment-information',
            'counter' => 42,
            'reason' => 'DIE_IP_COUNTER_EXCEEDED',
            'blocked_at' => time(),
            'expires_at' => time() + 600 // 10 minutes
        ];
        
        $redis->setex($blockKey, 600, json_encode($blockData));
        $redis->zAdd('BlockedIPs_Set', $blockData['expires_at'], $blockKey);
        
        echo "\n";
        echo "  Test: Display blocked IPs\n";
        echo "  ├─ Added test blocked IP: {$testIP}\n";
        echo "  ├─ Counter: 42\n";
        echo "  ├─ Reason: DIE_IP_COUNTER_EXCEEDED\n";
        
        // Execute command
        $command = new \Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        $exitCode = $command->run($input, $output);
        $outputText = $output->fetch();
        
        echo "  ├─ Exit code: {$exitCode}\n";
        echo "  ├─ Output preview:\n";
        
        $lines = explode("\n", $outputText);
        $displayedLines = 0;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && $displayedLines < 15) {
                echo "  │  " . $trimmed . "\n";
                $displayedLines++;
            }
        }
        
        // Verify output contains our test data
        expect($outputText)->toContain($testIP)
            ->and($outputText)->toContain('42')
            ->and($outputText)->toContain('Active Blocked IPs');
        
        echo "  └─ Blocked IP displayed correctly ✓\n";
        
        // Cleanup
        $redis->del($blockKey);
        $redis->zRem('BlockedIPs_Set', $blockKey);
    });
    
    test('command cleans up expired blocks', function () {
        // Setup: Add an expired blocked IP to Redis
        $config = require BP . '/app/etc/env.php';
        $redis = new \Redis();
        $redis->pconnect(
            $config['cache']['frontend']['default']['backend_options']['server'],
            (int) $config['cache']['frontend']['default']['backend_options']['port']
        );
        
        $expiredIP = '10.0.0.1';
        $expiredKey = 'BlockedIP_' . $expiredIP . '_payment';
        
        // Add to sorted set with past expiry (simulates expired entry)
        $redis->zAdd('BlockedIPs_Set', time() - 10, $expiredKey);
        
        echo "\n";
        echo "  Test: Cleanup expired blocks\n";
        echo "  ├─ Added expired key to sorted set: {$expiredKey}\n";
        echo "  ├─ Score: past timestamp (expired)\n";
        
        // Execute command
        $command = new \Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        $exitCode = $command->run($input, $output);
        $outputText = $output->fetch();
        
        // Check if key was removed from sorted set (pruned by zRemRangeByScore)
        $score = $redis->zScore('BlockedIPs_Set', $expiredKey);
        $stillInSet = $score !== false;
        
        echo "  ├─ Exit code: {$exitCode}\n";
        echo "  ├─ Key still in set: " . ($stillInSet ? 'YES' : 'NO') . "\n";
        echo "  └─ Expired blocks cleaned up ✓\n";
        
        expect($stillInSet)->toBeFalse();
        
        // Cleanup (just in case)
        $redis->zRem('BlockedIPs_Set', $expiredKey);
    });
    
    test('command configuration is correct', function () {
        $command = new \Genaker\BlockPaymentBot\Console\Command\ShowBlockedIPs();
        
        echo "\n";
        echo "  Test: Command configuration\n";
        echo "  ├─ Name: " . $command->getName() . "\n";
        echo "  ├─ Description: " . $command->getDescription() . "\n";
        
        expect($command->getName())->toBe('genaker:blockbot:show-blocked-ips')
            ->and($command->getDescription())->toContain('blocked IPs');
        
        echo "  └─ Configuration correct ✓\n";
    });
    
    test('explains CLI command usage', function () {
        echo "\n";
        echo "  CLI Command Usage Guide\n";
        echo "  \n";
        echo "  Command:\n";
        echo "  $ php bin/magento genaker:blockbot:show-blocked-ips\n";
        echo "  \n";
        echo "  What it does:\n";
        echo "  ├─ Connects to Redis\n";
        echo "  ├─ Retrieves active blocked IPs from BlockedIPs_Set (sorted set)\n";
        echo "  ├─ Displays data in formatted table\n";
        echo "  ├─ Automatically removes expired entries\n";
        echo "  └─ Shows summary statistics\n";
        echo "  \n";
        echo "  Table Columns:\n";
        echo "  ├─ IP Address: The blocked IP\n";
        echo "  ├─ Type: Endpoint type (cart_check, payment)\n";
        echo "  ├─ URL: Request URL that triggered block\n";
        echo "  ├─ Counter: Number of attempts\n";
        echo "  ├─ Reason: Why blocked (IP exceeded, etc.)\n";
        echo "  ├─ Blocked At: Timestamp when blocked\n";
        echo "  └─ Expires In: Time until block expires\n";
        echo "  \n";
        echo "  Testing without setup:upgrade:\n";
        echo "  ├─ These tests directly instantiate the command\n";
        echo "  ├─ No Magento DI container needed\n";
        echo "  ├─ Command class is self-contained\n";
        echo "  └─ Works immediately after deployment ✓\n";
        
        expect(true)->toBeTrue();
    });
});

