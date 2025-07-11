## ✨ **Why Laravel Errly?**

- 🚨 **Instant Slack alerts** - Get notified the moment errors happen
- ⚡ **Simple setup** - Add one line to `bootstrap/app.php` and configure your webhook
- 🎨 **Beautiful notifications** - Rich, actionable Slack messages with context
- 🛡️ **Smart filtering** - Only get alerts for errors that matter
- 🚀 **Laravel 12 native** - Built for modern Laravel architecture
- 🆓 **Free & open source** - No subscription fees or limits

> **Note**: Currently# 🚨 Laravel Errly

[![Latest Version on Packagist](https://img.shields.io/packagist/v/errly/laravel-errly.svg?style=flat-square)](https://packagist.org/packages/errly/laravel-errly)
[![Total Downloads](https://img.shields.io/packagist/dt/errly/laravel-errly.svg?style=flat-square)](https://packagist.org/packages/errly/laravel-errly)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeromecoloma/laravel-errly/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeromecoloma/laravel-errly/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeromecoloma/laravel-errly/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeromecoloma/laravel-errly/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)

**Early error detection and beautiful Slack notifications for Laravel 12 applications.**

Laravel Errly is the simplest way to get **instant Slack notifications** when critical errors occur in your Laravel application. Built specifically for Laravel 12's modern architecture with **minimal setup** - just one line of code and a Slack webhook.

---

## ✨ **Why Laravel Errly?**

- 🚨 **Instant Slack alerts** - Get notified the moment errors happen
- ⚡ **Simple setup** - Add one line to `bootstrap/app.php` and configure your webhook
- 🎨 **Beautiful notifications** - Rich, actionable Slack messages with context
- 🛡️ **Smart filtering** - Only get alerts for errors that matter
- 🚀 **Laravel 12 native** - Built for modern Laravel architecture
- 🆓 **Free & open source** - No subscription fees or limits

> **📢 Currently supports Slack notifications.** Discord, Teams, and email support are planned for future releases.

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

## 🧪 **Testing**

Laravel Errly includes comprehensive testing tools:

```bash
# Test your Slack integration
php artisan errly:test

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
