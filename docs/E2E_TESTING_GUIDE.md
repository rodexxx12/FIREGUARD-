# End-to-End Testing Guide

## Overview

End-to-end tests verify complete user workflows from start to finish, testing the entire system integration.

## Test Structure

```
tests/e2e/
├── FireDetectionFlowTest.php      # Fire detection and alert workflow
├── UserRegistrationFlowTest.php   # User registration and login
├── NotificationFlowTest.php       # SMS and email notifications
└── DeviceManagementFlowTest.php   # Device registration and management
```

## Test Scenarios

### 1. Fire Detection Flow (3 scenarios)
- ✅ Complete fire detection and alert flow
- ✅ Normal conditions (no alert)
- ✅ Warning conditions (alert but not emergency)

### 2. User Registration Flow (3 scenarios)
- ✅ Complete registration with email verification
- ✅ Duplicate email rejection
- ✅ Invalid data rejection

### 3. Notification Flow (4 scenarios)
- ✅ SMS notification flow
- ✅ Email notification flow
- ✅ Parallel SMS sending (multi-curl)
- ✅ Background SMS processing (async)

### 4. Device Management Flow (3 scenarios)
- ✅ Complete device registration
- ✅ Device status updates
- ✅ Device deactivation

**Total:** 13 E2E test scenarios defined

---

## Running E2E Tests

### Run All E2E Tests:
```bash
./vendor/bin/phpunit --testsuite E2E
```

### Run Specific E2E Test:
```bash
./vendor/bin/phpunit tests/e2e/FireDetectionFlowTest.php
```

---

## Setup Requirements

### Option 1: HTTP Client (Simpler)

Use Guzzle HTTP client to test API endpoints:

```bash
composer require --dev guzzlehttp/guzzle
```

**Pros:** Faster, easier to set up  
**Cons:** Can't test JavaScript interactions

### Option 2: Browser Automation (Complete)

Use Selenium or Puppeteer for full browser testing:

```bash
# Install Selenium PHP client
composer require --dev php-webdriver/webdriver

# Or use Panther (Symfony's browser testing)
composer require --dev symfony/panther
```

**Pros:** Tests complete UI including JavaScript  
**Cons:** Slower, more complex setup

---

## Test Environment Setup

### 1. Test Database
- Create separate test database
- Import schema
- Add test data

### 2. Test Server
- Run local server: `php -S localhost:8000`
- Or use existing XAMPP setup
- Configure TEST_API_URL in .env.testing

### 3. Test Services
- SMS: Use test API or mock service
- Email: Use Mailtrap.io or similar
- Configure in .env.testing

---

## Example E2E Test Implementation

### Using HTTP Client (Guzzle):

```php
<?php
namespace Tests\E2E;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class FireDetectionFlowTest extends TestCase
{
    private $client;
    
    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost/DEFENDED/',
            'cookies' => true,
        ]);
    }
    
    public function testFireDetectionFlow(): void
    {
        // 1. POST sensor data
        $response = $this->client->post('device/smoke_api.php', [
            'form_params' => [
                'device_id' => 'test_device',
                'smoke' => 3000,
                'temp' => 85,
                'flame_detected' => 1,
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        
        // 2. Verify fire_data created
        // (Query test database)
        
        // 3. Verify alert visible in dashboard
        $response = $this->client->get('userdashboard/');
        $this->assertStringContainsString('EMERGENCY', $response->getBody());
    }
}
```

---

## Test Data Management

### Creating Test Data:

```php
protected function createTestDevice(): array
{
    // Create device in test database
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("INSERT INTO devices (device_name, serial_number, user_id, status) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Test Device', 'TEST001', 1, 'active']);
    
    return [
        'device_id' => $conn->lastInsertId(),
        'device_name' => 'Test Device',
        'serial_number' => 'TEST001',
    ];
}
```

### Cleaning Up Test Data:

```php
protected function tearDown(): void
{
    // Clean up after each test
    $conn = getDatabaseConnection();
    $conn->exec("DELETE FROM devices WHERE serial_number LIKE 'TEST%'");
    $conn->exec("DELETE FROM fire_data WHERE device_id IN (SELECT device_id FROM devices WHERE serial_number LIKE 'TEST%')");
}
```

---

## Best Practices

### 1. Isolation
- Each test should be independent
- Clean up test data after each test
- Don't rely on test execution order

### 2. Realistic Data
- Use realistic sensor values
- Test edge cases
- Test error conditions

### 3. Assertions
- Verify database state
- Verify API responses
- Verify UI elements (if using browser)

### 4. Performance
- E2E tests are slow
- Run unit tests first
- Run E2E tests before deployment

---

## Coverage Goals

### E2E Test Coverage:

**Critical Flows (Must Test):**
- ✅ Fire detection and alert
- ✅ User registration
- ✅ User login
- ✅ Device registration
- ✅ SMS notifications

**Important Flows (Should Test):**
- Email notifications
- Alert acknowledgment
- Dashboard access
- Data visualization

**Nice to Have:**
- Admin functions
- Report generation
- Settings management

---

## Running E2E Tests in CI/CD

### GitHub Actions Example:

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: fireguard_test
    
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - run: composer install
      - run: php -S localhost:8000 &
      - run: ./vendor/bin/phpunit --testsuite E2E
```

---

## Current Status

### ✅ Complete:
- E2E test structure created
- 4 test files with 13 scenarios
- Test documentation
- PHPUnit configuration updated

### ⚠️ Pending:
- Browser automation or HTTP client setup
- Test database setup
- Complete test implementations
- Run E2E tests

**Estimated Time:** 4-6 hours to complete

---

## Next Steps

1. Choose testing approach (HTTP client or browser)
2. Set up test environment
3. Implement test scenarios
4. Run E2E tests
5. Fix any issues found
6. Add to CI/CD pipeline

---

**E2E test infrastructure is ready! Set up environment and implement tests!** 🧪✅










