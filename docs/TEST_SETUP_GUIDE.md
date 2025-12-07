# Test Setup Guide

## Quick Start

### 1. Install PHPUnit

PHPUnit is already in `composer.json`:
```bash
composer install
```

### 2. Create Test Database

```sql
-- Create test database
CREATE DATABASE fireguard_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema from main database
-- Option 1: Export from main DB and import to test DB
mysqldump -u root fireguard > schema.sql
mysql -u root fireguard_test < schema.sql

-- Option 2: Use your existing database creation scripts
```

### 3. Configure Test Environment

```bash
# Copy example file
cp tests/.env.testing.example tests/.env.testing

# Edit with your test database credentials
nano tests/.env.testing
```

**Important:** Use a SEPARATE test database, not production!

### 4. Run Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific suite
./vendor/bin/phpunit --testsuite Security
```

---

## Test Database Setup

### Option 1: Automated Setup (Recommended)

Create `tests/setup_test_db.php`:
```php
<?php
// This would create test database and seed test data
// Run once before testing
```

### Option 2: Manual Setup

1. Create database: `fireguard_test`
2. Import schema from production
3. Add test data:
   - Test users
   - Test devices
   - Test buildings

---

## Writing Tests

### Test Structure

```php
<?php
namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    public function testSomething(): void
    {
        $result = functionToTest($input);
        $this->assertEquals($expected, $result);
    }
}
```

### Running Individual Tests

```bash
# Run single test file
./vendor/bin/phpunit tests/unit/Security/InputSanitizerTest.php

# Run single test method
./vendor/bin/phpunit --filter testSanitizeString
```

---

## Coverage Goals

### Target: 70% Code Coverage

**Priority 1: Security Modules (90%+)**
- Input Sanitization ✅ (11 tests created)
- CSRF Protection ⚠️ (needs completion)
- XSS Protection (pending)
- Rate Limiting (pending)

**Priority 2: Authentication (80%+)**
- User authentication ✅ (3 tests created)
- Password hashing (pending)
- Session management (pending)

**Priority 3: Core Modules (70%+)**
- Database operations (structure ready)
- Configuration (pending)
- Logging (pending)

---

## Test Data

### Creating Test Fixtures

```php
// Example: Create test user
protected function createTestUser(): array
{
    return [
        'username' => 'testuser_' . time(),
        'email' => 'test_' . time() . '@example.com',
        'password' => 'TestPass123!',
    ];
}
```

### Cleanup After Tests

```php
protected function tearDown(): void
{
    // Clean up test data
    // $this->conn->exec("DELETE FROM users WHERE username LIKE 'testuser_%'");
}
```

---

## CI/CD Integration

### GitHub Actions Example

`.github/workflows/tests.yml`:
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: fireguard_test
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - run: composer install
      - run: composer test
```

---

## Current Status

### ✅ Complete:
- PHPUnit configuration
- Bootstrap file
- Test directory structure
- Initial test files (15+ tests)
- Test documentation

### ⚠️ Pending:
- Test database setup
- Complete database tests
- Expand test coverage
- Measure coverage
- Reach 70% target

---

## Next Steps

1. Set up test database
2. Complete database integration tests
3. Add more security tests
4. Add validation tests
5. Run coverage report
6. Expand until 70% reached

**Estimated Time:** 6-10 hours

---

## Support

For questions or issues:
1. Check `tests/README.md`
2. Review test examples in `tests/unit/`
3. See PHPUnit documentation: https://phpunit.de/

---

**Test infrastructure is ready! Set up test database and expand tests!** 🧪✅










