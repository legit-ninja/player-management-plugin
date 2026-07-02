<?php
/**
 * Base test case class for InterSoccer Player Management Plugin tests
 * Provides common utilities and helpers for all tests
 */

use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class InterSoccer_Test_Case extends PHPUnit_TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $GLOBALS['wp_stub_users'] = [];
        $GLOBALS['wp_stub_logged_in'] = false;
        $GLOBALS['wp_stub_current_user_id'] = 0;
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        $GLOBALS['wp_stub_user_caps'] = [];
        $GLOBALS['wp_stub_is_admin'] = false;
        $GLOBALS['wp_stub_actions'] = [];
    }

    /**
     * Teardown after each test
     */
    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Create mock user data
     * 
     * @param int $user_id
     * @param array $data
     * @return array
     */
    protected function createMockUser($user_id = 1, $data = [])
    {
        return array_merge([
            'ID' => $user_id,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'user_registered' => '2023-01-01 00:00:00',
            'roles' => ['customer'],
        ], $data);
    }

    /**
     * Create mock player data
     * 
     * @param array $overrides
     * @return array
     */
    protected function createMockPlayer($overrides = [])
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => '2015-05-15',
            'gender' => 'male',
            'avs_number' => '756.1234.5678.90',
            'medical_conditions' => '',
            'creation_timestamp' => time(),
        ], $overrides);
    }

    /**
     * Create mock WooCommerce order
     * 
     * @param int $order_id
     * @param array $data
     * @return Mockery\MockInterface
     */
    protected function createMockOrder($order_id = 1, $data = [])
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn($order_id);
        $order->shouldReceive('get_status')->andReturn($data['status'] ?? 'completed');
        $order->shouldReceive('get_customer_id')->andReturn($data['customer_id'] ?? 1);
        $order->shouldReceive('get_items')->andReturn($data['items'] ?? []);
        
        return $order;
    }

    /**
     * Create mock order item
     * 
     * @param int $item_id
     * @param array $meta
     * @return Mockery\MockInterface
     */
    protected function createMockOrderItem($item_id = 1, $meta = [])
    {
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('get_id')->andReturn($item_id);
        
        // Setup meta data
        foreach ($meta as $key => $value) {
            $item->shouldReceive('get_meta')->with($key)->andReturn($value);
        }
        
        return $item;
    }

    /**
     * Mock WordPress user meta functions
     * 
     * @param int $user_id
     * @param string $key
     * @param mixed $value
     */
    protected function mockUserMeta($user_id, $key, $value)
    {
        WP_Mock::userFunction('get_user_meta', [
            'args' => [$user_id, $key, true],
            'return' => $value,
        ]);
    }

    /**
     * Mock update user meta
     * 
     * @param int $user_id
     * @param string $key
     * @param mixed $value
     * @param bool $success
     */
    protected function mockUpdateUserMeta($user_id, $key, $value, $success = true)
    {
        WP_Mock::userFunction('update_user_meta', [
            'args' => [$user_id, $key, $value],
            'return' => $success,
        ]);
    }

    /**
     * Mock WordPress translation function
     * 
     * @param string $text
     * @param string $domain
     * @return string
     */
    protected function mockTranslation($text, $domain = 'player-management')
    {
        WP_Mock::userFunction('__', [
            'args' => [$text, $domain],
            'return' => $text,
        ]);
    }

    /**
     * Mock current_time function
     * 
     * @param string $format
     * @return string
     */
    protected function mockCurrentTime($format = 'mysql')
    {
        $time = $format === 'mysql' ? '2023-06-15 12:00:00' : time();
        
        WP_Mock::userFunction('current_time', [
            'args' => [$format],
            'return' => $time,
        ]);
    }

    /**
     * Assert array has keys
     * 
     * @param array $keys
     * @param array $array
     */
    protected function assertArrayHasKeys(array $keys, array $array)
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, "Array missing key: $key");
        }
    }

    /**
     * Create mock validation errors
     * 
     * @param array $errors
     * @return array
     */
    protected function createValidationErrors($errors = [])
    {
        return $errors;
    }

    /**
     * Set user meta values via wp-stubs global store.
     *
     * @param int   $user_id User ID.
     * @param array $map     Meta key => value map.
     */
    protected function setUserMeta(int $user_id, array $map): void
    {
        if (!isset($GLOBALS['wp_stub_users'][$user_id])) {
            $GLOBALS['wp_stub_users'][$user_id] = ['meta' => []];
        }

        foreach ($map as $key => $value) {
            $GLOBALS['wp_stub_users'][$user_id]['meta'][$key] = $value;
        }
    }

    /**
     * Configure logged-in state for wp-stubs.
     *
     * @param bool $logged_in Whether the user is logged in.
     * @param int  $user_id   Current user ID when logged in.
     */
    protected function setLoggedIn(bool $logged_in, int $user_id = 1): void
    {
        $GLOBALS['wp_stub_logged_in'] = $logged_in;
        $GLOBALS['wp_stub_current_user_id'] = $logged_in ? $user_id : 0;
    }

    /**
     * @deprecated Use setUserMeta() — WP_Mock cannot override wp-stub functions.
     */
    protected function mockGetUserMeta(int $user_id, array $map): void
    {
        $this->setUserMeta($user_id, $map);
    }

    /**
     * Mock wc_get_orders to return orders regardless of query args.
     *
     * @param array $orders Order mocks or arrays.
     */
    protected function mockWcGetOrders(array $orders): void
    {
        WP_Mock::userFunction('wc_get_orders', [
            'return' => $orders,
        ]);
    }
}

