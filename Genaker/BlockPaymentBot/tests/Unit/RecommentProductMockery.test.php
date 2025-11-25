<?php
/**
 * Pest test for Betanet\Bloomreach\Model\RecommentProduct class
 * 
 * ✨ MOCKERY VERSION - Clean, type-safe mocks without manual class extensions
 * 
 * Benefits over anonymous classes:
 * - Less boilerplate code
 * - No need to manually extend real classes
 * - Industry-standard mocking approach
 * - Built-in expectations and assertions
 * 
 * Run: vendor/bin/pest Unit/RecommentProductMockery.test.php
 */

use Mockery;

/**
 * Test helper using Mockery for clean, type-safe mocks
 */
class RecommentProductTestHelperMockery
{
    private $actualInstance;
    private $reflection;
    
    public function __construct($itemIds = '', $currentUrl = '', $refUrl = '')
    {
        // Create type-safe mocks using Mockery - much cleaner!
        $cacheMock = Mockery::mock(\Magento\Framework\App\CacheInterface::class);
        $cacheMock->shouldReceive('load')->andReturn(false)->byDefault();
        $cacheMock->shouldReceive('save')->andReturn(true)->byDefault();
        
        $serializerMock = Mockery::mock(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializerMock->shouldReceive('serialize')->andReturnUsing(function($data) {
            return json_encode($data);
        })->byDefault();
        $serializerMock->shouldReceive('unserialize')->andReturnUsing(function($string) {
            return json_decode($string, true);
        })->byDefault();
        
        $requestMock = Mockery::mock(\Magento\Framework\App\RequestInterface::class);
        
        // For classes with complex constructors, use partialMock to keep real methods
        $bloomreachHelperMock = Mockery::mock(\Betanet\Bloomreach\Helper\Data::class);
        $bloomreachHelperMock->shouldReceive('getSettings')->andReturn([
            'accountid' => '12345',
            'domain_key' => 'test_domain'
        ])->byDefault();
        $bloomreachHelperMock->shouldReceive('getRecommendationProductAttributes')
            ->andReturn('pid,title,price,thumb_image')
            ->byDefault();
        
        $curlFactoryMock = Mockery::mock(\Magento\Framework\HTTP\Client\CurlFactory::class);
        $curlFactoryMock->shouldReceive('create')->andReturn(new \stdClass())->byDefault();
        
        // Create REAL RecommentProduct instance with MOCKERY mocks
        $this->actualInstance = new \Betanet\Bloomreach\Model\RecommentProduct(
            $cacheMock,
            $serializerMock,
            $requestMock,
            $bloomreachHelperMock,
            $curlFactoryMock
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

// Clean up Mockery after each test
afterEach(function () {
    Mockery::close();
});

// ========== PEST TESTS ==========

describe('RecommentProduct::getCacheKey() - Mockery Version', function () {
    test('different product URLs generate different cache keys', function () {
        $itemIds = 'tile-123';
        $url1 = 'https://tilebar.com/product/tile-123.html';
        $url2 = 'https://tilebar.com/special/tile-123.html';
        $refUrl = 'https://tilebar.com/category';
        
        $instance1 = new RecommentProductTestHelperMockery($itemIds, $url1, $refUrl);
        $instance2 = new RecommentProductTestHelperMockery($itemIds, $url2, $refUrl);
        
        $params1 = $instance1->getApiParams();
        $params2 = $instance2->getApiParams();
        
        $cacheKey1 = $instance1->getCacheKey();
        $cacheKey2 = $instance2->getCacheKey();
        
        echo "\n";
        echo "  ✨ Mockery Version - Type-safe mocks with less code!\n";
        echo "  ├─ Item IDs: {$itemIds}\n";
        echo "  ├─ URL 1: {$url1}\n";
        echo "  ├─ URL 2: {$url2}\n";
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
        
        $instance1 = new RecommentProductTestHelperMockery($itemIds, $url, $refUrl1);
        $instance2 = new RecommentProductTestHelperMockery($itemIds, $url, $refUrl2);
        
        $cacheKey1 = $instance1->getCacheKey();
        $cacheKey2 = $instance2->getCacheKey();
        
        expect($cacheKey1)->not->toBe($cacheKey2);
    });
});

describe('RecommentProduct::getApiParams() - Mockery Version', function () {
    test('params are sorted alphabetically', function () {
        $instance = new RecommentProductTestHelperMockery('item-123', 'https://tilebar.com', 'https://google.com');
        $params = $instance->getApiParams();
        
        $keys = array_keys($params);
        $sortedKeys = $keys;
        sort($sortedKeys);
        
        expect($keys)->toBe($sortedKeys);
    });
    
    test('includes all required parameters', function () {
        $instance = new RecommentProductTestHelperMockery('item-456');
        $params = $instance->getApiParams();
        
        expect($params)->toHaveKeys(['fields', 'rows', 'start', '_br_uid_2', 'account_id', 'domain_key', 'item_ids']);
    });
    
    test('excludes empty optional parameters', function () {
        $instance = new RecommentProductTestHelperMockery('item-789');
        $params = $instance->getApiParams();
        
        expect($params)->not->toHaveKey('url')
            ->and($params)->not->toHaveKey('ref_url');
    });
});

describe('RecommentProduct - Cache Key Validation (Mockery)', function () {
    test('cache keys are always valid MD5 hashes', function () {
        $instances = [
            new RecommentProductTestHelperMockery('item-1', 'https://example.com', 'https://ref.com'),
            new RecommentProductTestHelperMockery('item-2', 'https://example.com'),
            new RecommentProductTestHelperMockery('item-3'),
        ];
        
        foreach ($instances as $instance) {
            $cacheKey = $instance->getCacheKey();
            expect($cacheKey)->toMatch('/^[a-f0-9]{32}$/');
        }
    });
    
    test('getApiParams returns properly formatted array', function () {
        $instance = new RecommentProductTestHelperMockery('item-test', 'https://tilebar.com', 'https://google.com');
        $params = $instance->getApiParams();
        
        expect($params)->toBeArray()
            ->and($params['fields'])->toBeString()
            ->and($params['rows'])->toBeString()
            ->and($params['account_id'])->toBeString()
            ->and($params['item_ids'])->toBeString();
    });
});


