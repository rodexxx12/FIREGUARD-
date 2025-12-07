# ⚙️ Optimization & Performance Testing Guide

**Purpose:** Ensure the application runs efficiently and can handle expected load.

---

## 📋 Pre-Deployment Performance Checklist

### ✅ Remove Unused Code
- [ ] No unused functions or classes
- [ ] No unused variables or imports
- [ ] No commented-out code blocks
- [ ] No debug/test files in production

**How to Test:**
```bash
# Find unused functions (manual review required)
php scripts/find_unused_code.php

# Find commented code
grep -r "^[[:space:]]*\/\/" --include="*.php" | wc -l

# Find unused imports
grep -r "^use " --include="*.php" | sort | uniq -c | sort -rn

# Check for test files
find . -name "*test*.php" -o -name "*Test*.php" | grep -v vendor | grep -v tests
```

**Expected Result:** Minimal unused code, no test files outside `tests/` directory

---

### ✅ Database Query Optimization
- [ ] Queries use proper indexes
- [ ] No N+1 query problems
- [ ] Pagination implemented for large datasets
- [ ] Connection pooling enabled (PDO singleton)

**How to Test:**
```bash
# Run database optimization script
php scripts/optimize-database.php

# Check for missing indexes
mysql -u root -p your_database -e "
SELECT 
    table_name, 
    index_name 
FROM information_schema.statistics 
WHERE table_schema = 'your_database' 
ORDER BY table_name, index_name;
"

# Enable query logging
# Add to MySQL config: slow_query_log = 1
# Then check: 
tail -f /var/log/mysql/slow-query.log
```

**Manual Tests:**
1. Profile slow queries: Add to code temporarily:
```php
$start = microtime(true);
// Your query here
$duration = (microtime(true) - $start) * 1000;
error_log("Query took: {$duration}ms");
```

2. Use EXPLAIN to analyze queries:
```sql
EXPLAIN SELECT * FROM users WHERE email = 'test@example.com';
```

**Expected Result:** All queries under 100ms, proper indexes exist

---

### ✅ Memory Usage
- [ ] No memory leaks
- [ ] Memory usage within limits
- [ ] Large datasets handled efficiently
- [ ] File uploads don't consume excessive memory

**How to Test:**
```bash
# Run memory usage checker
php scripts/check-memory-usage.php

# Monitor memory in real-time (add to code)
echo "Memory: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";
echo "Peak: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";

# Check for memory leaks
grep -r "while.*true" --include="*.php"
grep -r "set_time_limit(0)" --include="*.php"
```

**Load Test Memory:**
```bash
# Apache Bench - Monitor memory during test
ab -n 1000 -c 10 https://yourdomain.com/
# While running, check: ps aux | grep php
```

**Expected Result:**
- Memory usage < 128MB per request
- No unbounded memory growth
- Peak memory reasonable for workload

---

### ✅ Caching
- [ ] API responses cached where appropriate
- [ ] Static content cached
- [ ] Database query results cached
- [ ] Cache invalidation strategy in place

**How to Test:**
```bash
# Check caching implementation
cat fireFighter/components/cache.php

# Test cache headers
curl -I https://yourdomain.com/static/image.png | grep -i "cache-control"

# Check if caching is working (response time should improve)
time curl https://yourdomain.com/api/data
time curl https://yourdomain.com/api/data  # Should be faster
```

**Expected Result:** Static assets cached, API responses cached appropriately

---

### ✅ Performance Profiling
- [ ] Critical code paths profiled
- [ ] Bottlenecks identified and optimized
- [ ] Response times within acceptable limits

**How to Test:**
```bash
# Built-in profiler (already in core/bootstrap.php)
# Enable debug mode in .env:
APP_DEBUG=true

# Check logs for profiling data
tail -f logs/php_errors.log | grep "Request profiling"

# Use Xdebug profiler (if installed)
php -d xdebug.profiler_enable=1 your-script.php

# Analyze with webgrind or qcachegrind
```

**Manual Profiling:**
```php
// Add to critical functions
$start = microtime(true);
// Your code here
$duration = (microtime(true) - $start) * 1000;
if ($duration > 100) {
    error_log("Slow operation: {$duration}ms");
}
```

**Expected Result:** 
- Page load < 2 seconds
- API response < 500ms
- Database queries < 100ms

---

### ✅ Asynchronous Operations
- [ ] Long-running tasks don't block responses
- [ ] Background jobs handled properly
- [ ] FastCGI finish request used for async operations

**How to Test:**
```bash
# Check async implementations
grep -r "fastcgi_finish_request" --include="*.php"
grep -r "ignore_user_abort" --include="*.php"
grep -r "curl_multi_init" --include="*.php"

# Review async patterns
cat userdashboard/alarm/alert.php  # FastCGI example
cat production/alarm/alarm.php     # Multi-cURL example
```

**Manual Test:**
1. Trigger SMS alert and check response time (should return immediately)
2. Check logs to verify SMS sent in background

**Expected Result:** User doesn't wait for slow operations

---

### ✅ Blocking Operations
- [ ] No synchronous external API calls in critical paths
- [ ] File operations don't block requests
- [ ] Database operations are optimized

**How to Test:**
```bash
# Find potentially blocking operations
grep -r "file_get_contents.*http" --include="*.php"
grep -r "curl_exec" --include="*.php" | grep -v "curl_multi"
grep -r "sleep(" --include="*.php"

# Check timeouts are set
grep -r "CURLOPT_TIMEOUT" --include="*.php"
```

**Expected Result:** All external calls have timeouts, async where possible

---

### ✅ Asset Compression
- [ ] CSS files minified
- [ ] JavaScript files minified
- [ ] Images optimized
- [ ] Gzip compression enabled

**How to Test:**
```bash
# Check minified assets
ls -lh build/css/*.min.css
ls -lh build/js/*.min.js

# Compare sizes
du -h build/css/custom.css
du -h build/css/custom.min.css

# Check gzip compression
curl -I -H "Accept-Encoding: gzip" https://yourdomain.com/build/css/custom.min.css | grep -i "content-encoding"

# Verify compression ratio
curl -I https://yourdomain.com/build/js/custom.min.js | grep -i "content-length"
```

**Expected Result:**
- CSS: 20-30% smaller when minified
- JS: 40-60% smaller when minified
- Gzip reduces by additional 60-70%

---

## 🛠️ Performance Testing Tools

### 1. **Apache Bench** (Simple Load Testing)
```bash
# Test 1000 requests with 10 concurrent
ab -n 1000 -c 10 https://yourdomain.com/

# Test with authentication
ab -n 100 -c 5 -C "session_id=your_session_cookie" https://yourdomain.com/dashboard
```

**Metrics to Check:**
- Requests per second (target: >100)
- Time per request (target: <100ms)
- Failed requests (target: 0)

---

### 2. **Siege** (Stress Testing)
```bash
# Install
sudo apt-get install siege

# Run test
siege -c 50 -t 30s https://yourdomain.com/

# Test multiple URLs
siege -c 20 -t 60s -f urls.txt
```

---

### 3. **Lighthouse** (Frontend Performance)
```bash
# Install
npm install -g lighthouse

# Run audit
lighthouse https://yourdomain.com --output html --output-path ./report.html

# Check scores (target: >90 for each)
# - Performance
# - Accessibility
# - Best Practices
# - SEO
```

---

### 4. **Blackfire.io** (PHP Profiling)
```bash
# Install Blackfire
# Sign up: https://blackfire.io

# Profile a page
blackfire curl https://yourdomain.com/

# Compare two runs
blackfire run php your-script.php
```

---

### 5. **New Relic** (APM - Production)
- Monitor real-time performance
- Track slow transactions
- Database query analysis
- Error tracking

---

## 📊 Performance Benchmarks

### Response Time Targets:
| Endpoint | Target | Acceptable | Slow |
|----------|--------|------------|------|
| Homepage | <500ms | <1s | >2s |
| API Endpoints | <200ms | <500ms | >1s |
| Database Queries | <50ms | <100ms | >200ms |
| File Uploads | <2s | <5s | >10s |

### Load Targets:
| Metric | Minimum | Target | Excellent |
|--------|---------|--------|-----------|
| Concurrent Users | 10 | 50 | 100+ |
| Requests/Second | 50 | 100 | 200+ |
| CPU Usage | <60% | <40% | <30% |
| Memory Usage | <80% | <60% | <50% |

---

## 🧪 Performance Test Scenarios

### Scenario 1: Homepage Load Test
```bash
ab -n 1000 -c 50 https://yourdomain.com/
```
**Expected:** <1s response time, 0 failed requests

### Scenario 2: User Login Flow
```bash
# Test login endpoint
ab -n 500 -c 25 -p login.txt -T application/x-www-form-urlencoded https://yourdomain.com/login
```

### Scenario 3: API Load Test
```bash
# Test fire data API
ab -n 2000 -c 100 https://yourdomain.com/api/fire-data
```

### Scenario 4: Database Heavy Operation
```bash
# Test user dashboard (lots of queries)
ab -n 100 -c 10 -C "session=..." https://yourdomain.com/dashboard
```

### Scenario 5: File Upload Test
```bash
# Test image upload
curl -X POST -F "file=@test.jpg" https://yourdomain.com/upload
```

---

## 📈 Performance Optimization Checklist

### Quick Wins:
- [x] ✅ Minify CSS/JS (Already done - `build/css/*.min.css`)
- [x] ✅ Use CDN for libraries (Already done - vendors)
- [ ] Enable Gzip compression (Server config needed)
- [ ] Add browser caching headers (Server config needed)
- [x] ✅ Optimize images (Already optimized - PNG files)

### Database:
- [x] ✅ Use prepared statements (Already done)
- [x] ✅ Connection pooling (PDO singleton)
- [ ] Add indexes on frequently queried columns
- [ ] Implement query caching
- [ ] Use pagination for large results

### Code:
- [ ] Lazy load images
- [ ] Defer non-critical JavaScript
- [ ] Use async/await for operations
- [ ] Implement service workers (PWA)
- [ ] Use HTTP/2 push

---

## 📊 Performance Testing Report Template

```markdown
# Performance Test Report

**Date:** YYYY-MM-DD
**Tester:** [Your Name]
**Environment:** [Production/Staging]
**Tool:** [Apache Bench/Siege/etc.]

## Test Configuration
- Concurrent Users: [Number]
- Total Requests: [Number]
- Duration: [Time]

## Results

### Response Times
- Average: [ms]
- Median: [ms]
- 95th Percentile: [ms]
- 99th Percentile: [ms]

### Throughput
- Requests/Second: [Number]
- Transfer Rate: [KB/s]

### Reliability
- Success Rate: [%]
- Failed Requests: [Number]
- Timeout Rate: [%]

### Resource Usage
- CPU: [%]
- Memory: [MB]
- Disk I/O: [MB/s]

## Bottlenecks Identified
1. [Description]
2. [Description]

## Recommendations
1. [Recommendation]
2. [Recommendation]
```

---

## 🚨 Performance Red Flags

⚠️ **Stop and optimize if:**
- Response time >2 seconds
- Failed requests >1%
- Memory usage >500MB per process
- CPU usage >80% sustained
- Database queries >500ms

---

**Last Updated:** December 2024




