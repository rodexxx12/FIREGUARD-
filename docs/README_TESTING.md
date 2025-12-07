# Testing Documentation

## Overview

This directory contains unit tests for the FIREGUARD Fire Detection System.

## Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap file
├── phpunit.xml                 # PHPUnit configuration
├── unit/                       # Unit tests
│   ├── Core/                   # Core module tests
│   ├── Security/               # Security module tests
│   └── Auth/                   # Authentication tests
└── pre_deployment/            # Pre-deployment checklists
```

## Running Tests

### Run All Tests
```bash
composer test
```

### Run with Coverage
```bash
composer test-coverage
```

### Run Specific Test Suite
```bash
./vendor/bin/phpunit --testsuite Security
./vendor/bin/phpunit --testsuite Authentication
```

### Run Single Test File
```bash
./vendor/bin/phpunit tests/unit/Security/InputSanitizerTest.php
```

## Test Coverage Goals

- **Target:** 70% code coverage
- **Priority:** Core modules (security, auth, database)
- **Current:** Initial tests created (coverage to be measured)

## Writing Tests

### Test Naming Convention
- Test files: `*Test.php`
- Test methods: `test*()` or use `@test` annotation

### Example Test
```php
<?php
namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class InputSanitizerTest extends TestCase
{
    public function testSanitizeStringRemovesNullBytes(): void
    {
        $input = "test\0string";
        $result = sanitizeString($input);
        $this->assertStringNotContainsString("\0", $result);
    }
}
```

## Test Database Setup

For tests requiring database:
1. Create `.env.testing` file
2. Configure test database credentials
3. Use separate test database (not production!)

## Current Test Status

- ✅ Test infrastructure created
- ✅ PHPUnit configured
- ✅ Initial tests for Security module
- ✅ Initial tests for Authentication
- ⚠️ Database tests require test DB setup
- ⚠️ Coverage measurement pending

## Next Steps

1. Set up test database
2. Complete database connection tests
3. Add more security module tests
4. Add validation module tests
5. Add rate limiting tests
6. Measure and improve coverage










