# 🧹 Code Readability & Consistency Testing Guide

**Purpose:** Ensure code is maintainable, readable, and follows consistent standards.

---

## 📋 Pre-Deployment Code Quality Checklist

### ✅ Naming Conventions
- [ ] Functions use camelCase (e.g., `getUserData()`)
- [ ] Classes use PascalCase (e.g., `UserController`)
- [ ] Constants use UPPER_SNAKE_CASE (e.g., `MAX_UPLOAD_SIZE`)
- [ ] Variables use camelCase (e.g., `$userName`)
- [ ] Database tables use snake_case (e.g., `user_devices`)

**How to Test:**
```bash
# Check function naming
grep -r "function [A-Z]" --include="*.php"  # Should return nothing (no PascalCase functions)

# Check class naming
grep -r "^class [a-z]" --include="*.php"  # Should return nothing (no lowercase classes)

# Manual review recommended for consistency
```

**Expected Result:** Consistent naming throughout codebase

---

### ✅ Meaningful Names
- [ ] Variable names describe their purpose
- [ ] Function names describe their action
- [ ] No single-letter variables (except loop counters)
- [ ] No abbreviations unless widely known

**Anti-Patterns to Avoid:**
```php
// ❌ BAD
function f($u) {
    $d = $u['data'];
    return $d;
}

// ✅ GOOD
function formatUserData($user) {
    $formattedData = $user['data'];
    return $formattedData;
}
```

**How to Test:**
```bash
# Look for potentially unclear names
grep -r "function [a-z]\{1,3\}(" --include="*.php"  # Very short function names
grep -r "\$[a-z]\{1\}[^a-z]" --include="*.php"  # Single-letter variables
```

---

### ✅ Function Size & Complexity
- [ ] Functions are < 50 lines (ideally < 30)
- [ ] Functions do one thing (Single Responsibility)
- [ ] Nesting depth < 4 levels
- [ ] Cyclomatic complexity < 10

**How to Test:**
```bash
# Run readability checker
composer check-readability

# Check for long functions (manual review)
# Look for functions > 200 lines

# Check nesting depth
grep -r "if.*if.*if.*if.*if" --include="*.php"  # 5+ levels of nesting
```

**Example Issues:**
- `reg/registration.php:3191` - 710 lines (needs refactoring)
- Functions with depth 6-8 (needs simplification)

**Expected Result:** Most functions < 50 lines, depth < 4

---

### ✅ Code Structure
- [ ] No duplicate code (DRY principle)
- [ ] Related functions grouped together
- [ ] Clear separation of concerns
- [ ] Logical file organization

**How to Test:**
```bash
# Find duplicate code patterns (manual review)
# Look for similar code blocks

# Check file organization
tree -L 3 core/  # Should show logical structure

# Verify modular structure
ls core/*/  # Each module should be self-contained
```

---

### ✅ Comments & Documentation
- [ ] PHPDoc comments for all public functions
- [ ] Complex logic has explanatory comments
- [ ] No commented-out code
- [ ] README files exist for major modules

**How to Test:**
```bash
# Check for PHPDoc comments
grep -c "\/\*\*" core/database/database.php  # Should have multiple

# Find commented-out code
grep -r "^[[:space:]]*\/\/ " --include="*.php" | wc -l

# Check for inline comments on complex logic
grep -r "\/\/ " core/ | grep -i "algorithm\|complex\|note"
```

**Good PHPDoc Example:**
```php
/**
 * Sanitize integer input
 * 
 * @param mixed $input Integer input
 * @return int Sanitized integer (0 if invalid)
 */
function sanitizeInt($input) {
    // Implementation
}
```

---

### ✅ Consistent Formatting
- [ ] Consistent indentation (4 spaces or 1 tab)
- [ ] Consistent brace placement (same style throughout)
- [ ] Consistent spacing around operators
- [ ] Line length < 120 characters (max 140)

**How to Test:**
```bash
# Run readability checker
composer check-readability

# Check for tabs vs spaces (should be consistent)
grep -P "^\t" --include="*.php" -r . | wc -l
grep -P "^    " --include="*.php" -r . | wc -l

# Check line length
grep -r "^.\{141,\}" --include="*.php" | wc -l  # Lines > 140 chars
```

**Current Issues:**
- ~500 lines exceed 140 characters (needs fixing)

---

### ✅ Linters & Formatters
- [ ] PHP CodeSniffer (PHPCS) configured
- [ ] PHP-CS-Fixer available
- [ ] PSR-12 standard enforced
- [ ] Automated formatting on commit (optional)

**How to Test:**
```bash
# Check if PHPCS is configured
cat .phpcs.xml

# Run PHPCS
vendor/bin/phpcs --standard=PSR12 core/

# Run PHP-CS-Fixer (dry run)
vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply fixes
vendor/bin/php-cs-fixer fix
```

**Configuration:**
```xml
<!-- .phpcs.xml -->
<?xml version="1.0"?>
<ruleset name="FireGuard">
    <rule ref="PSR12"/>
    <file>core/</file>
    <file>device/</file>
    <exclude-pattern>vendor/*</exclude-pattern>
</ruleset>
```

---

### ✅ Style Guide Compliance
- [ ] Follows PSR-12 standard
- [ ] Consistent with project conventions
- [ ] New code matches existing style

**PSR-12 Key Rules:**
1. Files use `<?php` or `<?=` tags
2. Files use UTF-8 without BOM
3. Side effects separated from declarations
4. Classes/methods follow naming conventions
5. Proper indentation and spacing

**How to Test:**
```bash
# Run PSR-12 check
vendor/bin/phpcs --standard=PSR12 core/

# Check specific issues
# - Opening braces on same line for methods
# - Use statements in alphabetical order
# - One class per file
```

**Expected Result:** Core modules are PSR-12 compliant ✅

---

## 🛠️ Code Quality Tools

### 1. **PHP CodeSniffer** (PHPCS)
```bash
# Install
composer require --dev squizlabs/php_codesniffer

# Check code
vendor/bin/phpcs --standard=PSR12 core/

# Auto-fix what's possible
vendor/bin/phpcbf --standard=PSR12 core/
```

---

### 2. **PHP-CS-Fixer** (Automatic Formatting)
```bash
# Install
composer require --dev friendsofphp/php-cs-fixer

# Create config file .php-cs-fixer.php
cat > .php-cs-fixer.php << 'EOF'
<?php
$finder = PhpCsFixer\Finder::create()
    ->in(['core', 'device', 'production'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);
EOF

# Run dry-run
vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply fixes
vendor/bin/php-cs-fixer fix
```

---

### 3. **PHPStan** (Static Analysis)
```bash
# Install
composer require --dev phpstan/phpstan

# Create config phpstan.neon
cat > phpstan.neon << 'EOF'
parameters:
    level: 5
    paths:
        - core
        - device
    excludePaths:
        - */vendor/*
EOF

# Run analysis
vendor/bin/phpstan analyse
```

---

### 4. **PHP Mess Detector** (PHPMD)
```bash
# Install
composer require --dev phpmd/phpmd

# Check code
vendor/bin/phpmd core/ text cleancode,codesize,controversial,design,naming,unusedcode
```

---

### 5. **Custom Readability Checker**
```bash
# Already exists in your project!
composer check-readability

# Or run directly
php tests/pre_deployment/code_readability_consistency_check.php
```

---

## 📊 Code Metrics to Track

### Complexity Metrics:
| Metric | Good | Acceptable | Needs Refactoring |
|--------|------|------------|-------------------|
| Lines per Function | <30 | <50 | >50 |
| Cyclomatic Complexity | <5 | <10 | >10 |
| Nesting Depth | <3 | <4 | >4 |
| Function Parameters | <3 | <5 | >5 |

### Quality Metrics:
| Metric | Target |
|--------|--------|
| Code Coverage | >70% |
| Documentation Coverage | >80% |
| Duplicate Code | <5% |
| Technical Debt Ratio | <5% |

---

## 🧪 Code Quality Test Scenarios

### Scenario 1: Check Core Modules
```bash
vendor/bin/phpcs --standard=PSR12 core/
```
**Expected:** ✅ 0 errors, 0 warnings

### Scenario 2: Check Application Files
```bash
vendor/bin/phpcs --standard=PSR12 device/ production/ userdashboard/
```
**Expected:** Some warnings acceptable, document for future fix

### Scenario 3: Run Static Analysis
```bash
vendor/bin/phpstan analyse --level=5 core/
```
**Expected:** 0 critical issues

### Scenario 4: Check for Long Functions
```bash
composer check-readability | grep "Function length"
```
**Expected:** Document any functions >200 lines

### Scenario 5: Verify Naming Conventions
```bash
grep -r "function [A-Z]" core/ --include="*.php"
```
**Expected:** Empty (all functions should be camelCase)

---

## 🔧 Refactoring Guidelines

### Breaking Down Large Functions:

**Before (Bad):**
```php
function handleRegistration() {
    // Validate input (50 lines)
    // Process data (100 lines)
    // Save to database (50 lines)
    // Send email (40 lines)
    // Log activity (20 lines)
    // 260 lines total!
}
```

**After (Good):**
```php
function handleRegistration() {
    $validatedData = validateRegistrationInput($_POST);
    $processedData = processRegistrationData($validatedData);
    $userId = saveRegistrationToDatabase($processedData);
    sendRegistrationConfirmationEmail($userId);
    logRegistrationActivity($userId);
}

function validateRegistrationInput($input) {
    // 30 lines
}

function processRegistrationData($data) {
    // 40 lines
}

function saveRegistrationToDatabase($data) {
    // 30 lines
}

function sendRegistrationConfirmationEmail($userId) {
    // 25 lines
}

function logRegistrationActivity($userId) {
    // 15 lines
}
```

---

### Reducing Nesting Depth:

**Before (Bad - Depth 6):**
```php
function processData($data) {
    if ($data) {
        if (isset($data['user'])) {
            if ($data['user']['active']) {
                if ($data['user']['verified']) {
                    if ($data['user']['role'] === 'admin') {
                        if ($data['user']['permissions']) {
                            // Do something
                        }
                    }
                }
            }
        }
    }
}
```

**After (Good - Depth 1-2):**
```php
function processData($data) {
    // Early returns
    if (!$data) return;
    if (!isset($data['user'])) return;
    
    $user = $data['user'];
    if (!$user['active'] || !$user['verified']) return;
    if ($user['role'] !== 'admin') return;
    if (!$user['permissions']) return;
    
    // Do something
}
```

---

## 📋 Code Review Checklist

### Before Committing:
- [ ] Run `vendor/bin/phpcs --standard=PSR12 [file]`
- [ ] Check function length (< 50 lines)
- [ ] Check nesting depth (< 4 levels)
- [ ] Add PHPDoc comments
- [ ] Remove debug code
- [ ] Remove commented code
- [ ] Check variable names are meaningful
- [ ] Run tests: `composer test`

---

## 📊 Code Quality Report Template

```markdown
# Code Quality Report

**Date:** YYYY-MM-DD
**Reviewer:** [Your Name]
**Scope:** [Module/Files reviewed]

## Metrics

### Complexity
- Functions > 50 lines: [Number]
- Functions with depth > 4: [Number]
- Cyclomatic complexity > 10: [Number]

### Standards Compliance
- PSR-12 Violations: [Number]
- Naming Convention Issues: [Number]
- Documentation Missing: [Number]

### Maintainability
- Duplicate Code Blocks: [Number]
- Unused Functions: [Number]
- Technical Debt Items: [Number]

## Issues Found
1. [File:Line] - [Issue description]
2. [File:Line] - [Issue description]

## Recommendations
1. Refactor [function] - too complex
2. Add documentation to [class]
3. Remove duplicate code in [files]

## Priority Actions
- HIGH: [Action]
- MEDIUM: [Action]
- LOW: [Action]
```

---

## 🚨 Code Quality Red Flags

⚠️ **Immediate attention needed if:**
- Function >200 lines
- Nesting depth >6
- Cyclomatic complexity >20
- No documentation for public APIs
- Duplicate code blocks >10 lines

---

## 📚 Resources

- [PSR-12: Extended Coding Style](https://www.php-fig.org/psr/psr-12/)
- [Clean Code PHP](https://github.com/jupeter/clean-code-php)
- [PHP: The Right Way](https://phptherightway.com/)

---

**Last Updated:** December 2024




