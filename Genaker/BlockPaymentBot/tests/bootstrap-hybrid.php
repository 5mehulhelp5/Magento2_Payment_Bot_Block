<?php
/**
 * HYBRID BOOTSTRAP: Load Magento classes WITHOUT PHPUnit conflict
 * 
 * Strategy:
 * 1. Load Pest's autoloader FIRST (with its PHPUnit 10+)
 * 2. Manually register Magento's autoloader BUT exclude PHPUnit classes
 * 3. Both can coexist - Pest's PHPUnit runs tests, Magento classes are available
 */

echo "\n========== HYBRID BOOTSTRAP: PEST + MAGENTO ==========\n";

// Set PEST environment variable to prevent die() calls in tested code
$_ENV['PEST'] = true;
echo "PEST mode enabled: die() calls will return values instead\n";

// Define BP constant
if (!defined('BP')) {
    define('BP', dirname(dirname(dirname(dirname(dirname(__DIR__))))));
    echo "BP defined as: " . BP . "\n";
}

// STEP 1: Load Pest's autoloader FIRST (includes PHPUnit 10+)
echo "Loading Pest dependencies (with PHPUnit 10+)...\n";
require_once __DIR__ . '/vendor/autoload.php';
echo "✓ Pest's PHPUnit loaded\n";

// STEP 2: Register custom autoloader for Magento classes (SKIP Magento's vendor/autoload.php!)
// We don't load Magento's vendor/autoload.php because it would override Pest's PHPUnit
echo "Registering custom Magento class autoloader (PHPUnit excluded)...\n";

// STEP 3: Custom autoloader for Magento classes only
spl_autoload_register(function ($class) {
    // SKIP PHPUnit classes - use Pest's version
    if (strpos($class, 'PHPUnit\\') === 0) {
        return false;
    }
    
    // Load any custom module from app/code (generic, no hardcoded vendors)
    // Try: app/code/Vendor/Module/... from namespace Vendor\Module\...
    $file = BP . '/app/code/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    // Load Magento framework classes
    if (strpos($class, 'Magento\\') === 0) {
        // Try generated code first
        $file = BP . '/generated/code/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        
        // Try app/code for custom Magento framework extensions
        $file = BP . '/app/code/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        
        // Try vendor/magento - convert CamelCase to module-name format
        // Example: Magento\Store\Model\ScopeInterface -> vendor/magento/module-store/Model/ScopeInterface.php
        $parts = explode('\\', $class);
        if (count($parts) >= 2) {
            // Convert second part to module name: Store -> module-store
            $moduleName = 'module-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $parts[1]));
            $subPath = implode('/', array_slice($parts, 2));
            $file = BP . '/vendor/magento/' . $moduleName . '/' . $subPath . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
            
            // Also try framework path: Magento\Framework\... -> vendor/magento/framework/...
            if ($parts[1] === 'Framework') {
                $subPath = implode('/', array_slice($parts, 2));
                $file = BP . '/vendor/magento/framework/' . $subPath . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
    }
    
    // Load PSR interfaces (needed for LoggerInterface, etc.)
    if (strpos($class, 'Psr\\') === 0) {
        $file = BP . '/vendor/' . strtolower(str_replace('\\', '/', $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
}, true, false); // Prepend = true, throw = false (don't throw on failure)

echo "✓ Magento class autoloader registered (PHPUnit excluded)\n";

// STEP 4: Verify our class is available
$classExists = class_exists('Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore');
echo "AbstractLoadBefore class exists: " . ($classExists ? 'YES ✓' : 'NO ✗') . "\n";

// Check PHPUnit version
$reflection = new ReflectionClass('PHPUnit\Framework\TestCase');
$phpunitFile = $reflection->getFileName();
echo "PHPUnit loaded from: " . (strpos($phpunitFile, 'tests/vendor') !== false ? 'PEST ✓' : 'MAGENTO ✗') . "\n";

echo "========== BOOTSTRAP COMPLETE ==========\n\n";

