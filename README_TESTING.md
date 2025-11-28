# 🧪 Testing Guide

## Quick Start

### Install PHPUnit

```bash
# Using Composer (recommended)
composer require --dev phpunit/phpunit ^9.5

# Or download phar
wget https://phar.phpunit.de/phpunit-9.5.phar
chmod +x phpunit-9.5.phar
mv phpunit-9.5.phar /usr/local/bin/phpunit
```

### Run Tests

```bash
# All tests
./vendor/bin/phpunit

# Specific test file
./vendor/bin/phpunit tests/Unit/Security/InputValidationTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Test Structure

```
tests/
├── Unit/              # Unit tests
│   └── Security/      # Security unit tests
├── Integration/       # Integration tests
├── Security/          # Security-specific tests
└── helpers/           # Test helper functions
```

## Writing Tests

### Example Unit Test

```php
<?php
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    public function testSomething()
    {
        $result = myFunction();
        $this->assertEquals('expected', $result);
    }
}
```

## Test Coverage

Target: **80%+ coverage** for critical modules

Run coverage report:
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Continuous Integration

Tests run automatically on:
- Every push to `main` or `develop`
- Every pull request
- See `.github/workflows/ci.yml` for configuration

