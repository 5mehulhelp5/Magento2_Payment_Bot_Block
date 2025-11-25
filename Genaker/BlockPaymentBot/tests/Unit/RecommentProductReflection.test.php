<?php
/**
 * Pest test for Betanet\Bloomreach\Model\RecommentProduct class
 * 
 * Uses REAL RecommentProduct class with MOCK dependencies
 * Same approach as AbstractLoadBefore.test.php
 * 
 * Run: vendor/bin/pest Unit/RecommentProductReflection.test.php
 */

// ========== MOCK CLASSES FOR MAGENTO DEPENDENCIES ==========

/**
 * Mock implementation of Magento\Framework\App\CacheInterface
 */
class MockCache implements \Magento\Framework\App\CacheInterface
{
    public function load($identifier) { return false; }
    public function save($data, $identifier, $tags = [], $lifeTime = null) { return true; }
    public function remove($identifier) { return true; }
    public function clean($tags = []) { return true; }
    public function getBackend() { return null; }
    public function getLowLevelFrontend() { return null; }
    public function getFrontend() { return null; }
}

/**
 * Mock implementation of Magento\Framework\Serialize\SerializerInterface
 */
class MockSerializer implements \Magento\Framework\Serialize\SerializerInterface
{
    public function serialize($data) { return json_encode($data); }
    public function unserialize($string) { return json_decode($string, true); }
}

/**
 * Mock implementation of Magento\Framework\App\RequestInterface
 */
class MockRequest implements \Magento\Framework\App\RequestInterface
{
    public function getModuleName() { return 'test'; }
    public function setModuleName($name) { return $this; }
    public function getActionName() { return 'test'; }
    public function setActionName($name) { return $this; }
    public function getParam($key, $defaultValue = null) { return $defaultValue; }
    public function setParams(array $params) { return $this; }
    public function getParams() { return []; }
    public function getCookie($name, $default = null) { return $default; }
    public function isSecure() { return false; }
    public function getControllerName() { return 'test'; }
    public function setControllerName($name) { return $this; }
    public function getRequestedActionName() { return 'test'; }
    public function getRequestedControllerName() { return 'test'; }
}

/**
 * Create mock Bloomreach Helper that extends real class
 * @return \Betanet\Bloomreach\Helper\Data
 */
function createMockBloomreachHelper()
{
    return new class extends \Betanet\Bloomreach\Helper\Data
    {
        public function __construct()
        {
            // Skip parent constructor to avoid dependencies
        }
        
        public function getSettings()
        {
            return [
                'accountid' => '12345',
                'domain_key' => 'test_domain'
            ];
        }
        
        public function getRecommendationProductAttributes(): ?string
        {
            return 'pid,title,price,thumb_image';
        }
    };
}

/**
 * Create mock Curl Factory that extends real class
 * @return \Magento\Framework\HTTP\Client\CurlFactory
 */
function createMockCurlFactory()
{
    return new class extends \Magento\Framework\HTTP\Client\CurlFactory
    {
        public function __construct()
        {
            // Skip parent constructor to avoid dependencies
        }
        
        public function create(array $data = [])
        {
            return new \stdClass();
        }
    };
}

// ========== TEST HELPER CLASS ==========

/**
 * Test helper to instantiate and test REAL RecommentProduct with mock dependencies
 */
class RecommentProductTestHelper
{
    private $actualInstance;
    private $reflection;
    
    public function __construct($itemIds = '', $currentUrl = '', $refUrl = '')
    {
        // Create REAL RecommentProduct instance with MOCK dependencies
        $this->actualInstance = new \Betanet\Bloomreach\Model\RecommentProduct(
            new MockCache(),
            new MockSerializer(),
            new MockRequest(),
            createMockBloomreachHelper(),
            createMockCurlFactory()
        );
        
        $this->reflection = new \ReflectionClass($this->actualInstance);
        
        // Set private properties using reflection
        $itemIdsProperty = $this->reflection->getProperty('_itemIds');
        $itemIdsProperty->setAccessible(true);
        $itemIdsProperty->setValue($this->actualInstance, $itemIds);
        
        $currentUrlProperty = $this->reflection->getProperty('_currentUrl');
        $currentUrlProperty->setAccessible(true);
        $currentUrlProperty->setValue($this->actualInstance, $currentUrl);
        
        $refUrlProperty = $this->reflection->getProperty('_refUrl');
        $refUrlProperty->setAccessible(true);
        $refUrlProperty->setValue($this->actualInstance, $refUrl);
    }
    
    public function getApiParams()
    {
        $method = $this->reflection->getMethod('getApiParams');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
    
    public function getCacheKey()
    {
        $method = $this->reflection->getMethod('getCacheKey');
        $method->setAccessible(true);
        return $method->invoke($this->actualInstance);
    }
}

// ========== PEST TESTS ==========

describe('RecommentProduct::getCacheKey() - Tests REAL Magento Object', function () {
    test('different product URLs generate different cache keys', function () {
        $itemIds = 'tile-123';
        $url1 = 'https://tilebar.com/product/tile-123.html';
        $url2 = 'https://tilebar.com/special/tile-123.html';
        $refUrl = 'https://tilebar.com/category';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $url1, $refUrl);
        $instance2 = new RecommentProductTestHelper($itemIds, $url2, $refUrl);
        
        $params1 = $instance1->getApiParams();
        $params2 = $instance2->getApiParams();
        
        $cacheKey1 = $instance1->getCacheKey();
        $cacheKey2 = $instance2->getCacheKey();
        
        echo "\n";
        echo "  Test: Different URLs with same item_ids\n";
        echo "  ├─ Item IDs: {$itemIds}\n";
        echo "  ├─ URL 1: {$url1}\n";
        echo "  ├─ URL 2: {$url2}\n";
        echo "  ├─ Ref URL: {$refUrl}\n";
        echo "  ├─ Cache Key 1: {$cacheKey1}\n";
        echo "  ├─ Cache Key 2: {$cacheKey2}\n";
        echo "  └─ Keys Different: " . ($cacheKey1 !== $cacheKey2 ? 'YES' : 'NO') . " ✓\n";
        
        expect($cacheKey1)->not->toBe($cacheKey2)
            ->and($params1['url'])->toBe($url1)
            ->and($params2['url'])->toBe($url2);
    });
    
    test('different referral URLs generate different cache keys', function () {
        $itemIds = 'tile-456';
        $url = 'https://tilebar.com/product.html';
        $refUrl1 = 'https://tilebar.com/category/tiles';
        $refUrl2 = 'https://tilebar.com/search?q=marble';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $url, $refUrl1);
        $instance2 = new RecommentProductTestHelper($itemIds, $url, $refUrl2);
        
        $cacheKey1 = $instance1->getCacheKey();
        $cacheKey2 = $instance2->getCacheKey();
        
        expect($cacheKey1)->not->toBe($cacheKey2);
    });
});

describe('RecommentProduct::getApiParams() - Tests REAL Magento Object', function () {
    test('params are sorted alphabetically', function () {
        $instance = new RecommentProductTestHelper('item-123', 'https://tilebar.com', 'https://google.com');
        $params = $instance->getApiParams();
        
        $keys = array_keys($params);
        $sortedKeys = $keys;
        sort($sortedKeys);
        
        expect($keys)->toBe($sortedKeys);
    });
    
    test('includes all required parameters', function () {
        $instance = new RecommentProductTestHelper('item-456');
        $params = $instance->getApiParams();
        
        expect($params)->toHaveKeys(['fields', 'rows', 'start', '_br_uid_2', 'account_id', 'domain_key', 'item_ids']);
    });
    
    test('excludes empty optional parameters', function () {
        $instance = new RecommentProductTestHelper('item-789'); // No URL or refUrl
        $params = $instance->getApiParams();
        
        expect($params)->not->toHaveKey('url')
            ->and($params)->not->toHaveKey('ref_url');
    });
});

describe('RecommentProduct - Real Traffic Sources', function () {
    test('same product from category, search, and email have different cache keys', function () {
        $itemIds = 'tile-456';
        $url = 'https://tilebar.com/marble-tile.html';
        
        // Traffic from category page
        $fromCategory = new RecommentProductTestHelper(
            $itemIds,
            $url,
            'https://tilebar.com/category/marble'
        );
        
        // Traffic from search
        $fromSearch = new RecommentProductTestHelper(
            $itemIds,
            $url,
            'https://tilebar.com/search?q=marble'
        );
        
        // Traffic from email (with UTM param, no referer)
        $fromEmail = new RecommentProductTestHelper(
            $itemIds,
            'https://tilebar.com/marble-tile.html?utm_source=email',
            ''
        );
        
        $key1 = $fromCategory->getCacheKey();
        $key2 = $fromSearch->getCacheKey();
        $key3 = $fromEmail->getCacheKey();
        
        echo "\n";
        echo "  Test: Same product from different traffic sources\n";
        echo "  ├─ Item IDs: {$itemIds}\n";
        echo "  ├─ Source 1 (Category):\n";
        echo "  │  ├─ URL: {$url}\n";
        echo "  │  ├─ Ref: https://tilebar.com/category/marble\n";
        echo "  │  └─ Cache Key: {$key1}\n";
        echo "  ├─ Source 2 (Search):\n";
        echo "  │  ├─ URL: {$url}\n";
        echo "  │  ├─ Ref: https://tilebar.com/search?q=marble\n";
        echo "  │  └─ Cache Key: {$key2}\n";
        echo "  ├─ Source 3 (Email):\n";
        echo "  │  ├─ URL: https://tilebar.com/marble-tile.html?utm_source=email\n";
        echo "  │  ├─ Ref: (empty)\n";
        echo "  │  └─ Cache Key: {$key3}\n";
        echo "  └─ All Keys Different: YES ✓\n";
        
        expect($key1)->not->toBe($key2)
            ->and($key1)->not->toBe($key3)
            ->and($key2)->not->toBe($key3);
    });
    
    test('UTM parameters in URL create unique cache entries', function () {
        $itemIds = 'tile-789';
        $baseUrl = 'https://tilebar.com/product.html';
        
        $noUTM = new RecommentProductTestHelper($itemIds, $baseUrl);
        $withUTM = new RecommentProductTestHelper($itemIds, $baseUrl . '?utm_source=facebook&utm_campaign=spring');
        
        expect($noUTM->getCacheKey())->not->toBe($withUTM->getCacheKey());
    });
});

describe('RecommentProduct - URL GET Parameters', function () {
    test('URL with GET parameters has different hash than without', function () {
        $itemIds = 'item-123';
        $urlWithoutParams = 'https://tilebar.com/product.html';
        $urlWithParams = 'https://tilebar.com/product.html?color=white&size=large';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $urlWithoutParams);
        $instance2 = new RecommentProductTestHelper($itemIds, $urlWithParams);
        
        $params1 = $instance1->getApiParams();
        $params2 = $instance2->getApiParams();
        
        $cacheKey1 = $instance1->getCacheKey();
        $cacheKey2 = $instance2->getCacheKey();
        
        echo "\n";
        echo "  Test: URL with GET parameters\n";
        echo "  ├─ Item IDs: {$itemIds}\n";
        echo "  ├─ URL without params: {$urlWithoutParams}\n";
        echo "  ├─ URL with params: {$urlWithParams}\n";
        echo "  ├─ API Params 1 (url): {$params1['url']}\n";
        echo "  ├─ API Params 2 (url): {$params2['url']}\n";
        echo "  ├─ Cache Key 1: {$cacheKey1}\n";
        echo "  ├─ Cache Key 2: {$cacheKey2}\n";
        echo "  └─ Keys Different: YES ✓\n";
        
        expect($cacheKey1)->not->toBe($cacheKey2);
    });
    
    test('different GET parameter values produce different hashes', function () {
        $itemIds = 'item-456';
        $url1 = 'https://tilebar.com/product.html?color=white';
        $url2 = 'https://tilebar.com/product.html?color=black';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $url1);
        $instance2 = new RecommentProductTestHelper($itemIds, $url2);
        
        expect($instance1->getCacheKey())->not->toBe($instance2->getCacheKey());
    });
    
    test('same URL with different parameter order has different hash', function () {
        $itemIds = 'item-789';
        $url1 = 'https://tilebar.com/product.html?color=white&size=large';
        $url2 = 'https://tilebar.com/product.html?size=large&color=white';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $url1);
        $instance2 = new RecommentProductTestHelper($itemIds, $url2);
        
        // URLs are different strings, so cache keys should differ
        expect($instance1->getCacheKey())->not->toBe($instance2->getCacheKey());
    });
});

describe('RecommentProduct - Edge Cases', function () {
    test('empty referral URL vs populated referral URL differ', function () {
        $itemIds = 'item-999';
        $url = 'https://tilebar.com/product.html';
        
        $noRef = new RecommentProductTestHelper($itemIds, $url, '');
        $withRef = new RecommentProductTestHelper($itemIds, $url, 'https://google.com');
        
        expect($noRef->getCacheKey())->not->toBe($withRef->getCacheKey());
    });
    
    test('identical params always produce same cache key', function () {
        $itemIds = 'item-consistent';
        $url = 'https://tilebar.com/test.html';
        $ref = 'https://tilebar.com/category';
        
        $instance1 = new RecommentProductTestHelper($itemIds, $url, $ref);
        $instance2 = new RecommentProductTestHelper($itemIds, $url, $ref);
        
        expect($instance1->getCacheKey())->toBe($instance2->getCacheKey());
    });
});

describe('RecommentProduct - Cache Key Validation', function () {
    test('cache keys are always valid MD5 hashes', function () {
        $instances = [
            new RecommentProductTestHelper('item-1', 'https://example.com', 'https://ref.com'),
            new RecommentProductTestHelper('item-2', 'https://example.com'),
            new RecommentProductTestHelper('item-3'),
        ];
        
        foreach ($instances as $instance) {
            $cacheKey = $instance->getCacheKey();
            expect($cacheKey)->toMatch('/^[a-f0-9]{32}$/');
        }
    });
    
    test('getApiParams returns properly formatted array', function () {
        $instance = new RecommentProductTestHelper('item-test', 'https://tilebar.com', 'https://google.com');
        $params = $instance->getApiParams();
        
        expect($params)->toBeArray()
            ->and($params['fields'])->toBeString()
            ->and($params['rows'])->toBeString()
            ->and($params['account_id'])->toBeString()
            ->and($params['item_ids'])->toBeString();
    });
});
