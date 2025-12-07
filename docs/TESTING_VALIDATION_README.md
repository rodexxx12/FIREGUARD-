# 🧪 Testing & Validation Guide

**Purpose:** Ensure all functionality works correctly through comprehensive automated testing.

---

## 📋 Pre-Deployment Testing Checklist

### ✅ Unit Tests
- [ ] All critical functions have unit tests
- [ ] Edge cases are tested
- [ ] Error conditions are tested
- [ ] Test coverage >70%

**Current Status:**
- ✅ PHPUnit configured (`tests/phpunit.xml`)
- ✅ 11 working unit tests (`InputSanitizerTest.php`)
- ✅ Test infrastructure complete
- ⏳ Expanding coverage to 70%

**How to Run:**
```bash
# Run all tests
composer test

# Run specific test file
composer test tests/unit/Security/InputSanitizerTest.php

# Run with coverage report
composer test-coverage

# Run with coverage HTML output
composer test-coverage-html

# View coverage
open coverage/html/index.html
```

---

### ✅ Integration Tests
- [ ] API endpoints tested
- [ ] Database operations tested
- [ ] External service integration tested
- [ ] Authentication flows tested

**Current Status:**
- ✅ Integration test structure created
- ✅ Test files: `ApiTest.php`, `DatabaseTest.php`, `UserFlowTest.php`, `DeviceApiTest.php`
- ⏳ Completing implementations

**How to Run:**
```bash
# Run integration tests
composer test tests/integration/

# Run specific integration test
./vendor/bin/phpunit tests/integration/ApiTest.php
```

---

### ✅ End-to-End Tests
- [ ] Complete user workflows tested
- [ ] Fire detection flow tested
- [ ] User registration flow tested
- [ ] Device management flow tested

**Current Status:**
- ✅ E2E test structure created
- ✅ Test scenarios defined (12+ scenarios)
- ⏳ Completing implementations

**How to Run:**
```bash
# Run E2E tests
composer test tests/e2e/

# Run specific E2E test
./vendor/bin/phpunit tests/e2e/FireDetectionFlowTest.php
```

---

### ✅ Test Coverage
- [ ] Coverage reports generated
- [ ] Coverage meets 70% target
- [ ] Critical paths have 100% coverage

**How to Check:**
```bash
# Generate coverage report
composer test-coverage

# Check coverage percentage
php scripts/check-coverage.php

# View detailed HTML report
composer test-coverage-html
open coverage/html/index.html
```

**Coverage Targets:**
| Module | Target | Critical |
|--------|--------|----------|
| Security | 100% | Yes |
| Authentication | 100% | Yes |
| Database | 90% | Yes |
| Validation | 90% | Yes |
| Business Logic | 70% | No |
| UI/Templates | 50% | No |

---

### ✅ CI/CD Pipeline
- [ ] Tests run automatically on commit
- [ ] All tests must pass before merge
- [ ] Coverage reports generated automatically

**Setup CI/CD:**

**GitHub Actions (`.github/workflows/tests.yml`):**
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        extensions: mbstring, mysqli, pdo_mysql
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
      
    - name: Run tests
      run: composer test
      
    - name: Check coverage
      run: composer test-coverage-check
```

---

### ✅ Rollback Testing
- [ ] Rollback scripts tested
- [ ] Database rollback tested
- [ ] Backup/restore verified

**How to Test:**
```bash
# Create backup
./scripts/create-backup.sh

# Test rollback procedure
./scripts/create-rollback.sh

# Verify backup integrity
php scripts/verify-backup.php
```

**Rollback Test Checklist:**
- [ ] Backup creation successful
- [ ] Restore from backup successful
- [ ] Database restored correctly
- [ ] Files restored correctly
- [ ] Application functional after rollback

---

## 🧪 Unit Testing Guide

### Writing Unit Tests

**Test Structure:**
```php
<?php
namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class InputSanitizerTest extends TestCase
{
    public function testSanitizeStringRemovesNullBytes()
    {
        // Arrange
        $input = "test\0string";
        
        // Act
        $result = sanitizeString($input);
        
        // Assert
        $this->assertEquals('teststring', $result);
    }
    
    public function testSanitizeIntConvertsString()
    {
        // Arrange
        $input = "123";
        
        // Act
        $result = sanitizeInt($input);
        
        // Assert
        $this->assertSame(123, $result);
        $this->assertIsInt($result);
    }
}
```

---

### Test Coverage Examples

**Security Functions (Priority: HIGH):**
```bash
# Test input sanitization
./vendor/bin/phpunit tests/unit/Security/InputSanitizerTest.php

# Test CSRF protection
./vendor/bin/phpunit tests/unit/Security/CSRFTest.php

# Test XSS protection
./vendor/bin/phpunit tests/unit/Security/XSSTest.php
```

**Database Functions (Priority: HIGH):**
```bash
# Test database connection
./vendor/bin/phpunit tests/unit/Database/DatabaseTest.php

# Test prepared statements
./vendor/bin/phpunit tests/unit/Database/PreparedStatementsTest.php
```

**Authentication (Priority: HIGH):**
```bash
# Test authentication
./vendor/bin/phpunit tests/unit/Auth/AuthenticationTest.php

# Test authorization
./vendor/bin/phpunit tests/unit/Auth/AuthorizationTest.php
```

---

## 🔗 Integration Testing Guide

### API Endpoint Testing

**Example: Test Health Endpoint**
```php
<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class ApiTest extends TestCase
{
    private $client;
    
    protected function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost',
            'http_errors' => false
        ]);
    }
    
    public function testHealthEndpointReturns200()
    {
        $response = $this->client->get('/health.php');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertEquals('ok', $data['status']);
    }
}
```

---

### Database Integration Testing

**Example: Test User Registration**
```php
public function testUserRegistrationInsertsData()
{
    // Start transaction
    $conn = getDatabaseConnection();
    $conn->beginTransaction();
    
    try {
        // Test registration
        $userData = [
            'email' => 'test@example.com',
            'password' => 'securepass123',
            'name' => 'Test User'
        ];
        
        // Call registration function
        $result = registerUser($userData);
        
        // Assert user created
        $this->assertTrue($result);
        
        // Verify in database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['test@example.com']);
        $user = $stmt->fetch();
        
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user['name']);
        
    } finally {
        // Rollback transaction
        $conn->rollBack();
    }
}
```

---

## 🔄 End-to-End Testing Guide

### Test Scenarios

**1. User Registration Flow:**
```php
public function testCompleteUserRegistrationFlow()
{
    // 1. Submit registration form
    // 2. Verify email sent
    // 3. Click verification link
    // 4. Verify account activated
    // 5. Login with new account
    // 6. Access dashboard
}
```

**2. Fire Detection Flow:**
```php
public function testFireDetectionAndAlertFlow()
{
    // 1. Device sends fire data
    // 2. System detects fire
    // 3. Alert triggered
    // 4. SMS sent to users
    // 5. Email sent to users
    // 6. Dashboard updated
    // 7. Firefighters notified
}
```

**3. Device Registration Flow:**
```php
public function testDeviceRegistrationFlow()
{
    // 1. User requests device
    // 2. Admin approves device
    // 3. Device credentials generated
    // 4. Device connects to system
    // 5. Device sends test data
    // 6. Data appears in dashboard
}
```

---

## 📊 Test Execution Strategy

### Local Testing (Before Commit)
```bash
# 1. Run unit tests (fast)
composer test

# 2. Check for errors
echo $?  # Should be 0

# 3. Run linter
vendor/bin/phpcs --standard=PSR12 core/

# 4. Check coverage (optional)
composer test-coverage-text
```

---

### Pre-Deploy Testing (Staging)
```bash
# 1. Full test suite
composer test

# 2. Integration tests
composer test tests/integration/

# 3. E2E tests (if available)
composer test tests/e2e/

# 4. Performance tests
ab -n 100 -c 10 https://staging.yourdomain.com/

# 5. Security scan
docker run -t owasp/zap2docker-stable zap-baseline.py -t https://staging.yourdomain.com
```

---

### Post-Deploy Testing (Production)
```bash
# 1. Smoke tests
curl -I https://yourdomain.com/health.php

# 2. Critical path testing
# - Test login
# - Test registration
# - Test fire alert
# - Test device API

# 3. Monitor logs
tail -f logs/php_errors.log

# 4. Check error rate
# Should be <1% of requests
```

---

## 🛠️ Testing Tools

### 1. **PHPUnit** (Unit & Integration Testing)
```bash
# Install
composer require --dev phpunit/phpunit ^9.5

# Configure
cat tests/phpunit.xml

# Run tests
./vendor/bin/phpunit
```

---

### 2. **Guzzle** (HTTP Client for API Testing)
```bash
# Install
composer require --dev guzzlehttp/guzzle

# Usage
$client = new \GuzzleHttp\Client();
$response = $client->get('https://api.example.com/endpoint');
```

---

### 3. **Mockery** (Mocking Framework)
```bash
# Install
composer require --dev mockery/mockery

# Usage
$mock = Mockery::mock('Database');
$mock->shouldReceive('query')->andReturn(true);
```

---

### 4. **PHP VCR** (Record HTTP Interactions)
```bash
# Install
composer require --dev php-vcr/php-vcr

# Usage - records API responses for repeatable tests
```

---

### 5. **Selenium** (Browser Automation for E2E)
```bash
# Install Selenium Server
# Download from: https://www.selenium.dev/downloads/

# PHP Selenium Client
composer require --dev php-webdriver/webdriver

# Run tests
php tests/e2e/selenium-test.php
```

---

## 📊 Test Report Template

```markdown
# Test Execution Report

**Date:** YYYY-MM-DD
**Environment:** [Local/Staging/Production]
**Executed By:** [Your Name]

## Summary
- Total Tests: [Number]
- Passed: [Number]
- Failed: [Number]
- Skipped: [Number]
- Duration: [Time]
- Coverage: [%]

## Unit Tests
- Passed: [X/Y]
- Failed: [List failed tests]

## Integration Tests
- Passed: [X/Y]
- Failed: [List failed tests]

## E2E Tests
- Passed: [X/Y]
- Failed: [List failed tests]

## Failed Tests Details

### Test: [Test Name]
- **Expected:** [Expected result]
- **Actual:** [Actual result]
- **Error:** [Error message]
- **Action:** [Fix required]

## Coverage Report
- Overall: [%]
- Security: [%]
- Database: [%]
- Business Logic: [%]

## Recommendations
1. [Recommendation]
2. [Recommendation]

## Sign-off
- [x] All critical tests passing
- [x] Coverage meets target
- [ ] Ready for deployment
```

---

## 🚨 Testing Red Flags

⚠️ **Do NOT deploy if:**
- Any test fails
- Coverage <70% for critical modules
- Integration tests not run
- Security tests fail
- No rollback test performed

---

## 📚 Testing Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://github.com/testdouble/contributing-tests/wiki/Testing-Principles)
- [PHP Testing Best Practices](https://phptherightway.com/#testing)

---

## ✅ Quick Testing Checklist

**Before Every Commit:**
- [ ] Run `composer test`
- [ ] All tests pass
- [ ] No new failing tests

**Before Every Deploy:**
- [ ] Full test suite passes
- [ ] Integration tests pass
- [ ] Coverage report reviewed
- [ ] Rollback tested

**After Every Deploy:**
- [ ] Smoke tests pass
- [ ] Critical paths verified
- [ ] Error logs checked
- [ ] Monitoring active

---

**Last Updated:** December 2024




