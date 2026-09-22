# 🚨 Laravel Errly

[![Latest Version on Packagist](https://img.shields.io/packagist/v/errly/laravel-errly.svg?style=flat-square)](https://packagist.org/packages/errly/laravel-errly)
[![Total Downloads](https://img.shields.io/packagist/dt/errly/laravel-errly.svg?style=flat-square)](https://packagist.org/packages/errly/laravel-errly)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeromecoloma/laravel-errly/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeromecoloma/laravel-errly/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeromecoloma/laravel-errly/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeromecoloma/laravel-errly/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)

**Early error detection, beautiful Slack notifications and heartbeat monitoring for Laravel 12 and 13 applications on PHP 8.2 through 8.5.**

Laravel Errly is the simplest way to get **instant Slack notifications** when critical errors occur in your Laravel application. Built for Laravel 12 and 13 with **minimal setup** - just one line of code and a Slack webhook.

---

## ✨ **Why Laravel Errly?**

- 🚨 **Instant Slack alerts** - Get notified the moment errors happen
- ⚡ **Simple setup** - Add one line to `bootstrap/app.php` and configure your webhook
- 🎨 **Beautiful notifications** - Rich, actionable Slack messages with context
- 🛡️ **Smart filtering** - Only get alerts for errors that matter
- 💓 **Heartbeats** - Get alerted when your scheduler or queue workers stop, even when nothing throws
- 🚀 **Laravel 12 & 13 ready** - Built for modern Laravel architecture
- 🆓 **Free & open source** - No subscription fees or limits

> **📢 Error notifications currently go to Slack.** Discord, Teams, and email support are planned for future releases. Heartbeats currently support [Healthchecks.io](https://healthchecks.io) (hosted or self-hosted), and other monitors can be plugged in.

---

## 🎯 **Quick Start**

Get Laravel Errly running in **under 2 minutes**:

### **1. Install**
```bash
composer require errly/laravel-errly
```

### **2. Publish Config**
```bash
php artisan vendor:publish --tag=laravel-errly-config
```

### **3. Add Your Slack Webhook**
```env
# .env
ERRLY_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### **4. Enable in Bootstrap**
```php
// bootstrap/app.php
use Errly\LaravelErrly\ErrlyServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    // ... other configuration
    ->withExceptions(function (Exceptions $exceptions): void {
        // Only configure Errly if the package is installed
        if (class_exists(ErrlyServiceProvider::class)) {
            ErrlyServiceProvider::configureExceptions($exceptions);
        }
    })
    ->create();
```

### **5. Test It!**
```bash
php artisan errly:test
```

**That's it!** You'll receive a beautiful Slack notification with error details.

---

## 📱 **What Your Slack Notifications Look Like**

When an error occurs, you'll receive rich notifications like this:

```
🚨 **CRITICAL Error in MyApp Production**

🔍 Error Details
Exception: Illuminate\Database\QueryException
Message: SQLSTATE[42S02]: Base table or view not found
File: /app/Http/Controllers/UserController.php
Line: 42
URL: https://myapp.com/users/123
Method: GET
User: john@example.com (ID: 1234)
Environment: production
Server: web-01

📋 Stack Trace
#0 /app/Http/Controllers/UserController.php(42): ...
#1 /app/vendor/laravel/framework/src/... 
[... truncated]
```

---

## ⚙️ **Configuration**

Laravel Errly works great out of the box, but you can customize everything:

```php
// config/errly.php
return [
    'enabled' => env('ERRLY_ENABLED', true),
    
    'slack' => [
        'webhook_url' => env('ERRLY_SLACK_WEBHOOK_URL'),
        'channel' => env('ERRLY_SLACK_CHANNEL', '#errors'),
        'username' => env('ERRLY_SLACK_USERNAME', 'Laravel Errly'),
        'emoji' => env('ERRLY_SLACK_EMOJI', '🚨'),
    ],
    
    'filters' => [
        'environments' => [
            'enabled' => env('ERRLY_FILTER_ENVIRONMENTS', true),
            'allowed' => explode(',', env('ERRLY_ALLOWED_ENVIRONMENTS', 'production,staging')),
        ],
        
        // Automatically ignores noise like 404s, validation errors
        'ignored_exceptions' => [
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            // ... more
        ],
        
        // High-priority alerts for critical errors
        'critical_exceptions' => [
            \Illuminate\Database\QueryException::class,
            \ErrorException::class,
            // ... more
        ],
    ],
    
    'rate_limiting' => [
        'enabled' => env('ERRLY_RATE_LIMITING', true),
        'max_per_minute' => env('ERRLY_MAX_PER_MINUTE', 10),
    ],
];
```

---

## 🚀 **Usage Examples**

### **Environment Variables**
```env
# Basic Setup
ERRLY_ENABLED=true
ERRLY_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../xxx

# Advanced Configuration
ERRLY_SLACK_CHANNEL=#production-errors
ERRLY_SLACK_USERNAME="MyApp Alerts"
ERRLY_SLACK_EMOJI=⚠️

# Environment Filtering (only report in production)
ERRLY_FILTER_ENVIRONMENTS=true
ERRLY_ALLOWED_ENVIRONMENTS=production,staging

# Rate Limiting (prevent spam)
ERRLY_RATE_LIMITING=true
ERRLY_MAX_PER_MINUTE=5

# Custom App Name
ERRLY_APP_NAME="My Awesome App"
```

### **Manual Error Reporting**
```php
use Errly\LaravelErrly\Facades\Errly;

try {
    // Risky operation
    $result = $this->processPayment($amount);
} catch (PaymentException $e) {
    // Report with custom context
    Errly::report($e, [
        'user_id' => auth()->id(),
        'amount' => $amount,
        'payment_method' => 'stripe',
    ]);
    
    // Handle gracefully
    return response()->json(['error' => 'Payment failed'], 500);
}
```

### **Testing Different Error Types**
```bash
# Test general errors
php artisan errly:test

# Test critical errors (database, fatal errors)
php artisan errly:test critical

# Test validation errors (should be ignored)
php artisan errly:test validation

# Test custom errors
php artisan errly:test custom
```

---

## 🛡️ **Security Features**

Laravel Errly automatically protects sensitive data:

- **🔒 Redacts passwords** - Never exposes authentication data
- **🔒 Filters headers** - Removes authorization tokens
- **🔒 Recursively sanitizes payloads** - Nested request secrets are redacted too
- **🔒 Configurable sensitive fields** - Define your own protected fields
- **🔒 Safe by default** - Conservative data collection

```php
// Sensitive fields are automatically redacted
'sensitive_fields' => [
    'password',
    'password_confirmation', 
    'token',
    'api_key',
    'credit_card',
    'ssn',
],
```

---

## ⚡ **Performance**

Laravel Errly is designed for **zero performance impact**:

- **Async notifications** - Won't slow down your app
- **Smart rate limiting** - Prevents notification spam
- **Efficient filtering** - Only processes errors that matter
- **Minimal memory usage** - Lightweight error context collection

---

## 🎛️ **Advanced Features**

### **Severity Levels**
Errors are automatically categorized:
- **🔴 CRITICAL** - Database errors, fatal errors, parse errors
- **🟡 HIGH** - HTTP 500+ errors
- **🟢 MEDIUM** - General exceptions, runtime errors

### **Context Collection**
Rich error context includes:
- **Request details** - URL, method, IP, user agent
- **User information** - ID, email, name (if authenticated)
- **Server information** - Hostname, environment
- **Stack traces** - Full error traces (configurable length)

### **Smart Filtering**
Automatically ignores noise:
- ✅ **404 errors** - Page not found
- ✅ **Validation errors** - Form validation failures
- ✅ **Auth errors** - Login failures
- ✅ **Rate limiting errors** - Too many requests

---

## 💓 **Heartbeat Monitoring**

An exception alert cannot tell you that nothing is running. If the scheduler or a queue worker dies, no error is thrown and no Slack message is sent. Heartbeats cover that gap: every minute Errly pings an outside monitor, and the monitor alerts you when the pings stop.

Heartbeats are off until you configure a check, so existing installs are unaffected. They need the scheduler running (`schedule:run` from cron, or `schedule:work`).

### **What gets pinged**

| Heartbeat | How it is sent | Stops when |
|-----------|----------------|------------|
| Scheduler | Inline, inside the scheduler | The scheduler stops running |
| Queue | A small job pushed to that queue | No worker is draining that queue |

A queued heartbeat is tried once and never reported as a failure, so an unreachable monitor does not fill `failed_jobs` or Slack. While a worker is down, only one heartbeat per queue waits in the queue.

Things to know:
- **Maintenance mode** - Laravel does not run scheduled tasks while the app is down (`php artisan down`), so every heartbeat stops and the monitor alerts. Pause the checks in your monitor for long maintenance windows.
- **`sync` queue driver** - jobs run straight away inside the scheduler, so a queue heartbeat would only prove the scheduler ran. Point queue heartbeats at a real queue connection.
- **Unique lock** - the one-waiting-heartbeat limit uses your default cache store. With several servers, that store must be shared (Redis, database, Memcached).
- **Monitoring tools** - the ping requests bypass Laravel's `Http` client hooks, so Telescope, Pulse and Nightwatch do not record them or the secret ping URL. The queued heartbeat job is a normal job, so Horizon, Telescope and Nightwatch will show one per queue per minute.
- **Keep checks secret** - anyone with a ping URL, UUID or ping key can ping your checks and hide an outage. Keep them in `.env`, not in committed config.

### **Setup with Healthchecks.io**

1. Create one check per heartbeat in [Healthchecks.io](https://healthchecks.io), with a **period of 1 minute** and a grace time that suits you (for example 5 minutes).
2. Add them to `.env`. A check can be a full ping URL, a check UUID, or a slug:

```env
# Full URL or UUID
ERRLY_HEARTBEAT_SCHEDULER=https://hc-ping.com/your-scheduler-uuid
ERRLY_HEARTBEAT_QUEUE_DEFAULT=your-default-queue-uuid

# Or slugs, with the project's ping key
ERRLY_HEALTHCHECKS_PING_KEY=your-project-ping-key
ERRLY_HEARTBEAT_SCHEDULER=myapp-scheduler
ERRLY_HEARTBEAT_QUEUE_DEFAULT=myapp-queue-default
```

3. Check each one reaches the monitor:

```bash
php artisan errly:heartbeat-test
```

This pings straight from the command, so it proves the checks exist, not that your workers run. Leave a check blank in local and staging to keep it off there.

### **More queues**

`ERRLY_HEARTBEAT_QUEUE_DEFAULT` covers the connection's default queue. Publish the config and add any other queue your workers listen on:

```php
'heartbeat' => [
    'queues' => [
        'default' => env('ERRLY_HEARTBEAT_QUEUE_DEFAULT'),
        'emails' => env('ERRLY_HEARTBEAT_QUEUE_EMAILS'),
    ],
    // Use a connection other than the default one
    'queue_connection' => env('ERRLY_HEARTBEAT_QUEUE_CONNECTION'),
],
```

### **Heartbeat options**

```env
ERRLY_HEARTBEAT_ENABLED=true              # Heartbeats only (ERRLY_ENABLED=false also stops them)
ERRLY_HEARTBEAT_CLIENT=healthchecks       # Which monitor to ping
ERRLY_HEALTHCHECKS_URL=https://hc-ping.com # Change for self-hosted Healthchecks
ERRLY_HEALTHCHECKS_TIMEOUT=5              # Seconds per ping
ERRLY_HEALTHCHECKS_ATTEMPTS=1             # Tries per ping (retries 5xx, 429 and network errors only)
ERRLY_HEALTHCHECKS_AUTO_PROVISION=false   # Create missing slug checks on first ping
```

> **⚠️ Auto-provisioning:** Healthchecks.io gives a check created this way a period of 1 day and a grace time of 1 hour, and the period cannot be set from the ping. Change it to 1 minute in the dashboard, or a dead worker can go unnoticed for about a day.

### **Other monitors**

Heartbeat clients are resolved with Laravel's `Manager`, so you can add your own from a service provider:

```php
use Errly\LaravelErrly\Heartbeat\HeartbeatClient;
use Errly\LaravelErrly\Heartbeat\HeartbeatManager;

app(HeartbeatManager::class)->extend('my-monitor', fn () => new class implements HeartbeatClient {
    public function ping(string $check): void
    {
        // Send the ping. Throw a HeartbeatException if it was not recorded.
    }
});
```

Then set `ERRLY_HEARTBEAT_CLIENT=my-monitor`.

> **💡 Monitoring a single scheduled task?** Laravel already has `->pingOnSuccess($url)` and `->pingOnFailure($url)` on scheduled events. Errly's heartbeats are for the scheduler and workers as a whole.

---

## 🧪 **Testing**

Laravel Errly includes comprehensive testing tools:

```bash
# Test your Slack integration
php artisan errly:test

# Test your heartbeat checks
php artisan errly:heartbeat-test

# Test specific error types
php artisan errly:test database
php artisan errly:test critical
php artisan errly:test validation

# Run the package test suite
composer test

# Check code quality
composer analyse
```

---

## 📋 **Requirements**

- **PHP 8.2+**
- **Laravel 12+**
- **Slack workspace** with webhook URL (Discord, Teams, Email coming soon)
- **Optional:** a [Healthchecks.io](https://healthchecks.io) account for heartbeats

---

## 🔧 **Installation & Setup**

### **Step 1: Install Package**
```bash
composer require errly/laravel-errly
```

### **Step 2: Publish Configuration**
```bash
php artisan vendor:publish --tag=laravel-errly-config
```

### **Step 3: Create Slack Webhook**
1. Go to [Slack API Apps](https://api.slack.com/apps)
2. Create new app → "From scratch"
3. Enable "Incoming Webhooks"
4. Add webhook to your desired channel
5. Copy the webhook URL

### **Step 4: Configure Environment**
```env
ERRLY_ENABLED=true
ERRLY_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
ERRLY_SLACK_CHANNEL=#errors
```

### **Step 5: Enable Exception Handling**
```php
// bootstrap/app.php
use Errly\LaravelErrly\ErrlyServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions): void {
        // Only configure Errly if the package is installed
        if (class_exists(ErrlyServiceProvider::class)) {
            ErrlyServiceProvider::configureExceptions($exceptions);
        }
    })
    ->create();
```

### **Step 6: Test**
```bash
php artisan errly:test
```

Check your Slack channel for the test notification!

---

## 🤝 **Contributing**

We love contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### **Development Setup**
```bash
git clone https://github.com/jeromecoloma/laravel-errly.git
cd laravel-errly
composer install
composer test
```

### **Running Tests**
```bash
composer test          # Run test suite
composer analyse       # Static analysis
composer format         # Code formatting
```

---

## 📝 **Changelog**

Please see [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## 🛠️ **Troubleshooting**

### **Not receiving Slack notifications?**

**1. Check your webhook URL**
```bash
# Test with curl
curl -X POST -H 'Content-type: application/json' \
  --data '{"text":"Test from curl"}' \
  YOUR_WEBHOOK_URL
```

**2. Verify configuration**
```bash
php artisan tinker
>>> config('errly.enabled')
>>> config('errly.slack.webhook_url')
```

**3. Check Laravel logs**
```bash
tail -f storage/logs/laravel.log
```

**4. Test manually**
```php
use Errly\LaravelErrly\Facades\Errly;
Errly::report(new Exception('Manual test'));
```

### **Too many notifications?**
Enable rate limiting:
```env
ERRLY_RATE_LIMITING=true
ERRLY_MAX_PER_MINUTE=5
```

### **Heartbeat check not receiving pings?**

Run `php artisan errly:heartbeat-test` and read the reason next to each `FAILED` line:
- `OK (not found)` - the UUID does not match a check in Healthchecks.io
- `404 not found` - the slug does not match a check (or turn on auto-provisioning)
- `409 ambiguous slug` - two checks in the project share that slug

If the test passes but a queue check still goes down, no worker is listening on that queue, or `schedule:run` is not running.

### **Notifications in development?**
Use environment filtering:
```env
ERRLY_FILTER_ENVIRONMENTS=true
ERRLY_ALLOWED_ENVIRONMENTS=production,staging
```

---

## 📄 **License**

Laravel Errly is open-sourced software licensed under the [MIT license](LICENSE.md).

---

## 🙏 **Credits**

- **Jerome Coloma** - Creator and maintainer
- **Laravel Community** - Inspiration and feedback
- **Spatie** - Package development tools

---

## 💝 **Support**

If Laravel Errly helps you catch errors early, consider:
- ⭐ **Starring the repo** on GitHub
- 🐦 **Sharing on Twitter** with #LaravelErrly
- 📝 **Writing a blog post** about your experience
- 💬 **Joining discussions** in Issues

---

<div align="center">

**Built with ❤️ for the Laravel community**

[⭐ Star on GitHub](https://github.com/jeromecoloma/laravel-errly) • [📦 View on Packagist](https://packagist.org/packages/errly/laravel-errly) • [🐛 Report Issues](https://github.com/jeromecoloma/laravel-errly/issues) • [💬 Discussions](https://github.com/jeromecoloma/laravel-errly/discussions)

</div>
