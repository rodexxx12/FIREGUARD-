# 📊 Monitoring Setup Guide

## Application Monitoring

### Error Monitoring

Errors are automatically logged to:
- `logs/php_errors.log` - General PHP errors
- `logs/device_api_errors.log` - Device API errors
- `logs/production_errors.log` - Production module errors

### Recommended Monitoring Tools

#### 1. Application Performance Monitoring (APM)

**Options:**
- **New Relic** - Comprehensive APM
- **DataDog** - Full-stack monitoring
- **Sentry** - Error tracking
- **Rollbar** - Error monitoring

**Setup Sentry (Example):**
```php
// Add to composer.json
"require": {
    "sentry/sentry": "^3.0"
}

// Initialize in bootstrap.php
Sentry\init(['dsn' => 'your-sentry-dsn']);
```

#### 2. Server Monitoring

Monitor:
- CPU usage
- Memory usage
- Disk space
- Network traffic
- Database connections

**Tools:**
- **Nagios** - Server monitoring
- **Zabbix** - Network monitoring
- **Prometheus** - Metrics collection

#### 3. Database Monitoring

Monitor:
- Query performance
- Connection pool
- Slow queries
- Table sizes

**Setup MySQL Slow Query Log:**
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries > 1 second
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
```

#### 4. Uptime Monitoring

**Services:**
- **UptimeRobot** - Free uptime monitoring
- **Pingdom** - Advanced monitoring
- **StatusCake** - Performance monitoring

## Log Rotation

### Linux (logrotate)

Create `/etc/logrotate.d/firedetection`:

```
/path/to/application/logs/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

### Windows (Task Scheduler)

Create PowerShell script to archive logs older than 7 days.

## Alerting

Set up alerts for:
- [ ] Error rate > threshold
- [ ] Response time > 2 seconds
- [ ] Disk space < 20%
- [ ] Database connection failures
- [ ] High memory usage
- [ ] Failed login attempts spike

## Health Check Endpoint

Create `health.php`:

```php
<?php
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check database
try {
    $conn = getDatabaseConnection();
    $conn->query('SELECT 1');
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = 'failed';
}

// Check disk space
$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskPercent = ($diskFree / $diskTotal) * 100;
$health['checks']['disk'] = [
    'status' => $diskPercent > 20 ? 'ok' : 'warning',
    'free_percent' => round($diskPercent, 2)
];

echo json_encode($health, JSON_PRETTY_PRINT);
```

Access at: `https://yourdomain.com/health.php`

## Metrics to Track

### Application Metrics
- Request rate
- Response time (p50, p95, p99)
- Error rate
- API endpoint usage

### Business Metrics
- Active users
- Device registrations
- Fire alerts
- Response times

### System Metrics
- CPU usage
- Memory usage
- Disk I/O
- Network traffic

## Dashboard Setup

Create monitoring dashboard with:
1. System health overview
2. Error trends
3. Performance metrics
4. User activity
5. Alert history

**Tools:**
- **Grafana** - Visualization
- **Kibana** - Log analysis
- **Custom Dashboard** - Build your own

