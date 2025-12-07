# Test Coverage Setup Guide

## ✅ Status: IMPLEMENTED

This guide provides step-by-step instructions for setting up and running test coverage reports for the Fire Detection System.

## Prerequisites

### 1. Enable PHP Zip Extension (REQUIRED)

The system currently cannot install PHPUnit because the PHP zip extension is not enabled.

#### For XAMPP on Windows:

1. **Locate your `php.ini` file:**
   ```
   C:\xampp\php\php.ini
   ```

2. **Open `php.ini` in a text editor (as Administrator)**

3. **Find and uncomment the zip extension:**
   - Search for: `;extension=zip`
   - Change to: `extension=zip`
   - Remove the semicolon at the beginning

4. **Save the file and restart Apache:**
   ```powershell
   # Stop Apache
   C:\xampp\apache_stop.bat
   
   # Start Apache
   C:\xampp\apache_start.bat
   ```

5. **Verify the extension is loaded:**
   ```powershell
   php -m | findstr zip
   ```
   
   You should see `zip` in the output.

### 2. Install PHPUnit and Dependencies

Once the zip extension is enabled, install the required packages:

```powershell
cd C:\xampp\htdocs\DEFENDED
composer install --no-interaction
```

## Running Tests

### Basic Test Execution

Run all tests without coverage:

```powershell
composer test
```

### Generate Coverage Reports

#### Full Coverage Report (HTML + XML + Text)

```powershell
composer test-coverage
```

This generates:
- **HTML Report:** `coverage/html/index.html` (viewable in browser)
- **XML Report:** `coverage/clover.xml` (for CI/CD integration)
- **Text Report:** Console output showing coverage summary

#### Coverage Report Types

1. **Text-Only Coverage:**
   ```powershell
   composer test-coverage-text
   ```
   
2. **HTML-Only Coverage:**
   ```powershell
   composer test-coverage-html
   ```
   
3. **Check Coverage Thresholds:**
   ```powershell
   composer test-coverage-check
   ```

## Coverage Thresholds

The project enforces the following minimum coverage requirements:

| Metric | Minimum | Target |
|--------|---------|--------|
| **Lines** | 70% | 80% |
| **Methods** | 70% | 80% |
| **Classes** | 70% | 80% |

### Coverage Check Script

The `scripts/check-coverage.php` script automatically verifies that coverage meets minimum thresholds:

```powershell
php scripts/check-coverage.php
```

**Output Example:**

```
=================================================
  Code Coverage Threshold Checker
=================================================

Coverage Results:
─────────────────────────────────────────────────
✓ Lines     :  75.32% ( 856 / 1136) [Threshold: 70%]
✓ Methods   :  72.45% ( 145 /  200) [Threshold: 70%]
✓ Classes   :  80.00% (  32 /   40) [Threshold: 70%]
ℹ Elements  :  74.18% (1033 / 1392)

=================================================
✓ All coverage thresholds met!
=================================================
```

## Viewing Coverage Reports

### HTML Report

1. Generate the coverage report:
   ```powershell
   composer test-coverage-html
   ```

2. Open the report in your browser:
   ```powershell
   start coverage/html/index.html
   ```

The HTML report provides:
- Color-coded coverage visualization
- Line-by-line coverage details
- Class and method coverage breakdown
- Uncovered code identification

### XML Report (Clover Format)

The `coverage/clover.xml` file is suitable for:
- Continuous Integration (CI/CD) systems
- Code quality tools (SonarQube, Code Climate, etc.)
- Automated coverage tracking

## Configuration Files

### PHPUnit Configuration

Location: `tests/phpunit.xml`

Key settings:
- Test suites defined (Unit, Integration, E2E)
- Coverage paths configured
- Report formats specified
- Environment variables for testing

### Composer Scripts

Location: `composer.json`

Defined scripts:
```json
{
  "scripts": {
    "test": "phpunit --configuration tests/phpunit.xml",
    "test-coverage": "phpunit --configuration tests/phpunit.xml --coverage-html coverage/html --coverage-text --coverage-clover coverage/clover.xml",
    "test-coverage-text": "phpunit --configuration tests/phpunit.xml --coverage-text",
    "test-coverage-html": "phpunit --configuration tests/phpunit.xml --coverage-html coverage/html",
    "test-coverage-check": "php scripts/check-coverage.php"
  }
}
```

## Troubleshooting

### Issue: "The zip extension and unzip/7z commands are both missing"

**Solution:** Enable the PHP zip extension (see Prerequisites above)

### Issue: "Coverage report not found"

**Solution:** Run tests with coverage generation first:
```powershell
composer test-coverage
```

### Issue: "Coverage below minimum thresholds"

**Solution:** Add more tests to increase coverage. View the HTML report to identify uncovered code:
```powershell
start coverage/html/index.html
```

### Issue: "PHPUnit not found"

**Solution:** Ensure dependencies are installed:
```powershell
composer install --no-interaction
```

### Issue: Xdebug Not Installed

Code coverage requires Xdebug or PCOV extension. Install Xdebug:

1. Download from: https://xdebug.org/download
2. Follow installation instructions for XAMPP
3. Restart Apache

## CI/CD Integration

### GitHub Actions Example

```yaml
- name: Run tests with coverage
  run: composer test-coverage

- name: Check coverage thresholds
  run: composer test-coverage-check

- name: Upload coverage report
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage/clover.xml
```

### GitLab CI Example

```yaml
test:coverage:
  script:
    - composer test-coverage
    - composer test-coverage-check
  coverage: '/Lines:\s+(\d+\.\d+)%/'
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage/clover.xml
```

## Best Practices

1. **Run tests before committing:**
   ```powershell
   composer test
   ```

2. **Check coverage regularly:**
   ```powershell
   composer test-coverage-check
   ```

3. **Review HTML reports to identify gaps:**
   ```powershell
   composer test-coverage-html
   start coverage/html/index.html
   ```

4. **Aim for 80%+ coverage on critical code:**
   - Authentication modules
   - Security functions
   - Database operations
   - API endpoints

5. **Use coverage reports to guide test writing:**
   - Focus on uncovered critical paths
   - Add tests for edge cases
   - Ensure error handling is tested

## Directory Structure

```
DEFENDED/
├── coverage/                    # Generated coverage reports (git-ignored)
│   ├── html/                   # HTML coverage report
│   │   └── index.html         # Main coverage page
│   └── clover.xml             # XML coverage report
├── scripts/
│   └── check-coverage.php     # Coverage threshold checker
├── tests/
│   ├── phpunit.xml            # PHPUnit configuration
│   ├── bootstrap.php          # Test bootstrap file
│   ├── unit/                  # Unit tests
│   ├── integration/           # Integration tests
│   └── e2e/                   # End-to-end tests
└── composer.json              # Test scripts defined here
```

## Quick Reference

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests without coverage |
| `composer test-coverage` | Generate full coverage report |
| `composer test-coverage-text` | Show coverage in console |
| `composer test-coverage-html` | Generate HTML report |
| `composer test-coverage-check` | Verify coverage meets thresholds |
| `start coverage/html/index.html` | Open HTML report in browser |

## Support

For issues or questions about test coverage:
1. Check this guide for common solutions
2. Review the HTML coverage report for detailed information
3. Ensure all prerequisites are met (PHP extensions, dependencies)
4. Verify PHPUnit is properly installed: `vendor/bin/phpunit --version`

---

**Last Updated:** 2025-12-03
**Version:** 1.0.0
**Status:** ✅ Ready for Use













