# Player Management Plugin - Testing Documentation

## Table of Contents
1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Test Structure](#test-structure)
4. [Running Tests](#running-tests)
5. [Writing New Tests](#writing-new-tests)
6. [Mocking Strategies](#mocking-strategies)
7. [Coverage Goals](#coverage-goals)
8. [Troubleshooting](#troubleshooting)

## Overview

This test suite provides comprehensive coverage of the Player Management plugin using PHPUnit and WP_Mock. The tests run independently of WordPress, making them fast and reliable for continuous integration.

### Technologies Used
- **PHPUnit 9**: Modern PHP testing framework
- **WP_Mock**: WordPress function mocking without requiring WP installation
- **Mockery**: Object mocking for WooCommerce and custom classes
- **Composer**: Dependency and script management

### Test Statistics
- **120+ test methods** across 10 test files
- **Target coverage: 85-90%** overall
- **Execution time: < 5 seconds** for full suite

## Quick Start

### Installation

1. Install dependencies:
```bash
cd /path/to/player-management
composer install
```

2. Verify PHPUnit is available:
```bash
vendor/bin/phpunit --version
```

### Running All Tests

```bash
composer test
```

Or directly with PHPUnit:
```bash
vendor/bin/phpunit
```

### Expected Output
```
PHPUnit 9.x.x by Sebastian Bergmann

..........................................................   58 / 120 ( 48%)
..........................................................  116 / 120 ( 96%)
....                                                        120 / 120 (100%)

Time: 00:04.123, Memory: 28.00 MB

OK (120 tests, 250 assertions)
```

## Test Structure

```
tests/
├── bootstrap.php                     # Test initialization, WP_Mock setup
├── README.md                         # This file
├── helpers/
│   └── TestCase.php                  # Base test class with utilities
├── fixtures/
│   ├── players.php                   # Sample player data
│   ├── orders.php                    # Sample WooCommerce orders
│   └── settings.php                  # Plugin settings
├── unit/                             # Pure logic tests
│   ├── ValidatorTest.php             # Validation logic (100% coverage)
│   ├── PlayerUtilsTest.php           # Utility functions (95%+)
│   ├── HelpersTest.php               # Global helpers (90%+)
│   ├── DatabaseTest.php              # Database operations (80%+)
│   └── AdminTest.php                 # Admin functionality (75%+)
└── integration/                      # Integration tests
    ├── AjaxPlayerHandlersTest.php    # Player AJAX (90%+)
    ├── AjaxAdminHandlersTest.php     # Admin AJAX (85%+)
    ├── AjaxCleanupHandlersTest.php   # Cleanup AJAX (85%+)
    ├── WooCommerceTest.php           # WC integration (75%+)
    └── DataDeletionTest.php          # Data deletion (75%+)
```

## Running Tests

### Run Specific Test Suites

**Unit tests only:**
```bash
composer test:unit
# or
vendor/bin/phpunit --testsuite=unit
```

**Integration tests only:**
```bash
composer test:integration
# or
vendor/bin/phpunit --testsuite=integration
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/unit/ValidatorTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter test_validate_player_data_with_valid_data
```

### Generate Coverage Reports

**HTML report (detailed):**
```bash
composer test:coverage
# Open: coverage/html/index.html
```

**Text report (terminal):**
```bash
composer test:coverage-text
```

### Debugging Tests

**Enable verbose output:**
```bash
vendor/bin/phpunit --verbose
```

**Stop on first failure:**
```bash
vendor/bin/phpunit --stop-on-failure
```

**Show test output (echo, var_dump):**
```bash
vendor/bin/phpunit --debug
```

## Writing New Tests

### 1. Choose Test Location

- **Unit Tests** (`tests/unit/`): Pure logic, no external dependencies
- **Integration Tests** (`tests/integration/`): Test interactions between components

### 2. Create Test Class

```php
<?php
/**
 * Tests for MyNewFeature
 * Target: 90%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/my-new-feature.php';

class MyNewFeatureTest extends InterSoccer_Test_Case
{
    private $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new My_New_Feature();
        
        // Mock WordPress functions
        WP_Mock::userFunction('__')->andReturnUsing(function($text) {
            return $text;
        });
    }

    /**
     * Test feature with valid input
     */
    public function test_feature_with_valid_input()
    {
        $result = $this->feature->process('valid data');
        $this->assertTrue($result);
    }

    /**
     * Test feature with invalid input
     */
    public function test_feature_with_invalid_input()
    {
        $result = $this->feature->process('');
        $this->assertFalse($result);
    }
}
```

### 3. Naming Conventions

**Test methods:**
- Start with `test_`
- Use descriptive names: `test_add_player_with_missing_required_fields`
- Be specific: `test_validate_age_boundary_min` (exactly 3 years old)

**Test classes:**
- Match the class being tested + `Test` suffix
- Example: `Player_Management_Utils` → `PlayerUtilsTest`

### 4. Assertion Examples

```php
// Boolean assertions
$this->assertTrue($result);
$this->assertFalse($result);

// Equality
$this->assertEquals('expected', $actual);
$this->assertNotEquals('expected', $actual);

// Type checking
$this->assertIsArray($result);
$this->assertIsString($result);
$this->assertInstanceOf(MyClass::class, $object);

// Array assertions
$this->assertArrayHasKey('key', $array);
$this->assertCount(5, $array);
$this->assertContains('value', $array);

// String assertions
$this->assertStringContainsString('needle', $haystack);
$this->assertStringStartsWith('prefix', $string);

// Exceptions
$this->expectException(InvalidArgumentException::class);
$this->expectExceptionMessage('Expected message');
```

## Mocking Strategies

### Mock WordPress Functions

```php
// Simple return value
WP_Mock::userFunction('get_option', [
    'args' => ['my_option'],
    'return' => 'option_value',
]);

// Return different values based on arguments
WP_Mock::userFunction('get_user_meta', [
    'args' => [1, 'intersoccer_players', true],
    'return' => $playerData,
]);

// Return callback result
WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(function($text) {
    return trim($text);
});

// Expect function to be called
WP_Mock::userFunction('wp_mail', [
    'times' => 1,  // exactly once
]);
```

### Mock WooCommerce Objects

```php
// Mock order
$mockOrder = Mockery::mock('WC_Order');
$mockOrder->shouldReceive('get_id')->andReturn(100);
$mockOrder->shouldReceive('get_status')->andReturn('completed');
$mockOrder->shouldReceive('get_customer_id')->andReturn(1);

// Mock order item
$mockItem = Mockery::mock('WC_Order_Item_Product');
$mockItem->shouldReceive('get_meta')
    ->with('Assigned Attendee')
    ->andReturn('John Doe');
```

### Mock User Meta

```php
// Using helper from TestCase
$this->mockUserMeta(1, 'intersoccer_players', $playerArray);

// Or manually
WP_Mock::userFunction('get_user_meta', [
    'args' => [1, 'intersoccer_players', true],
    'return' => $playerArray,
]);
```

### Mock Globals

```php
// Mock wpdb
global $wpdb;
$wpdb = Mockery::mock('wpdb');
$wpdb->prefix = 'wp_';
$wpdb->shouldReceive('get_results')->andReturn($results);
```

### Mock $_POST Data

```php
protected function setUp(): void
{
    parent::setUp();
    $_POST = [];
}

public function test_ajax_handler()
{
    $_POST = [
        'nonce' => 'valid_nonce',
        'user_id' => 1,
        'action' => 'test_action',
    ];
    
    // ... test code
}

protected function tearDown(): void
{
    $_POST = [];
    parent::tearDown();
}
```

## Coverage Goals

### Target Coverage by Component

| Component | Target | Status |
|-----------|--------|--------|
| Validator Class | 100% | ✅ |
| Player Utils | 95%+ | ✅ |
| Global Helpers | 90%+ | ✅ |
| Player AJAX | 90%+ | ✅ |
| Admin AJAX | 85%+ | ✅ |
| Cleanup AJAX | 85%+ | ✅ |
| Database | 80%+ | ✅ |
| Admin | 75%+ | ✅ |
| WooCommerce | 75%+ | ✅ |
| Data Deletion | 75%+ | ✅ |
| **Overall** | **85-90%** | **✅** |

### What to Test

**✅ Always test:**
- Public methods and functions
- Success paths (happy path)
- Error handling and validation
- Edge cases and boundary conditions
- Security (nonce, permissions)

**❌ Don't test:**
- Third-party library code
- WordPress core functions
- Simple getters/setters
- Private methods (test through public interface)

## Troubleshooting

### Common Issues

**1. "Class not found" error**
```
Solution: Check require_once paths in test file
require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/class-name.php';
```

**2. "WP_Mock not found" error**
```bash
Solution: Install dependencies
composer install
```

**3. Tests pass but coverage is 0%**
```
Solution: Install Xdebug or PCOV
# Ubuntu/Debian
sudo apt-get install php-xdebug

# macOS
brew install php@7.4
pecl install xdebug
```

**4. "Cannot redeclare function" error**
```
Solution: Function already loaded, check if file included twice
or use function_exists() guard in source file
```

**5. Mock expectations not met**
```
Solution: Check WP_Mock::tearDown() is called in tearDown()
and expectations match actual calls
```

### Debug Tips

**Print mock expectations:**
```php
WP_Mock::userFunction('my_function', [
    'times' => 1,
    'return' => 'value',
]);

// Enable WP_Mock debugging
WP_Mock::setUp();
```

**Check what was called:**
```php
// Use Mockery's debugging
$mock = Mockery::spy('WC_Order');
// ... run test
$mock->shouldHaveReceived('get_id')->once();
```

**Isolate failing test:**
```bash
vendor/bin/phpunit --filter test_specific_failing_test
```

## Best Practices

### 1. Keep Tests Fast
- Use WP_Mock instead of real WordPress
- Mock external dependencies
- Avoid file I/O when possible
- Target: < 5 seconds for full suite

### 2. Keep Tests Independent
- Each test should run in isolation
- Don't rely on test execution order
- Clean up in `tearDown()`
- Use fresh fixtures for each test

### 3. Write Meaningful Tests
- Test behavior, not implementation
- Use descriptive test names
- One assertion per concept
- Cover both positive and negative cases

### 4. Use Fixtures
```php
// Good: Use fixtures
$playerData = require __DIR__ . '/../fixtures/players.php';
$validPlayer = $playerData['valid_player'];

// Bad: Inline data
$playerData = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    // ... lots of data
];
```

### 5. Test Edge Cases
- Empty strings, null, 0, false
- Boundary values (min-1, min, max, max+1)
- Invalid input types
- Large datasets
- Concurrent operations

## CI/CD Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: PHPUnit Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, xml
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: composer test
      
      - name: Generate coverage
        run: composer test:coverage-text
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        if: matrix.php-version == '7.4'
```

## Continuous Improvement

### Adding Tests for New Features

1. **Write test first** (TDD approach)
2. **Ensure it fails** (red)
3. **Implement feature**
4. **Make test pass** (green)
5. **Refactor** (clean up)

### Maintaining Tests

- Update tests when requirements change
- Remove obsolete tests
- Refactor duplicated test code
- Keep fixtures up to date
- Review coverage reports regularly

### Code Review Checklist

- [ ] New code has tests
- [ ] Tests cover success and failure
- [ ] Edge cases tested
- [ ] Descriptive test names
- [ ] No hard-coded values
- [ ] Tests pass locally
- [ ] Coverage maintained/improved

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WP_Mock Documentation](https://github.com/10up/wp_mock)
- [Mockery Documentation](http://docs.mockery.io/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

## Support

For questions or issues with the test suite:
1. Check this documentation
2. Review existing tests for examples
3. Check PHPUnit/WP_Mock documentation
4. Contact the development team

---

**Happy Testing!** 🚀

