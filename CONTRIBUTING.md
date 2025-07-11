# Contributing to Laravel Errly

Thank you for considering contributing to Laravel Errly! 🎉

We welcome contributions from everyone. This document will help you get started.

## 🚀 **Getting Started**

### **Development Environment Setup**

1. **Fork and clone the repository:**
   ```bash
   git clone https://github.com/YOUR-USERNAME/laravel-errly.git
   cd laravel-errly
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up testing environment:**
   ```bash
   # Create a test Laravel app (optional, for integration testing)
   cd ..
   composer create-project laravel/laravel test-errly-app
   cd test-errly-app
   composer config repositories.laravel-errly path "../laravel-errly"
   composer require errly/laravel-errly:@dev
   ```

## 🧪 **Testing**

Before submitting changes, make sure all tests pass:

```bash
# Run the test suite
composer test

# Run static analysis
composer analyse

# Fix code style
composer format

# Test in actual Laravel app
cd ../test-errly-app
php artisan errly:test
```

### **Writing Tests**

We use **Pest** for testing. When adding features:

1. **Add unit tests** for service classes
2. **Add feature tests** for end-to-end functionality
3. **Test error conditions** and edge cases
4. **Maintain 80%+ test coverage**

Example test structure:
```php
// tests/Unit/ErrorFilterServiceTest.php
test('it filters validation exceptions', function () {
    $service = new ErrorFilterService();
    $exception = new ValidationException(validator([], []));
    
    expect($service->shouldReport($exception))->toBeFalse();
});
```

## 📝 **Code Style**

We follow **PSR-12** coding standards with **Laravel Pint**:

```bash
# Fix code style automatically
composer format

# Check for style issues
vendor/bin/pint --test
```

### **Coding Guidelines**

- **Use type hints** for all parameters and return types
- **Write descriptive method names** - `shouldReportException()` not `check()`
- **Add PHPDoc blocks** for complex methods
- **Keep methods focused** - single responsibility principle
- **Use early returns** to reduce nesting
- **Follow Laravel conventions** for naming and structure

## 🐛 **Bug Reports**

When reporting bugs, please include:

### **Required Information**
- **Laravel version** (e.g., 12.0)
- **PHP version** (e.g., 8.2.0)
- **Laravel Errly version** (e.g., 1.0.0)
- **Steps to reproduce** the issue
- **Expected behavior** vs **actual behavior**
- **Error messages** or stack traces

### **Bug Report Template**
```markdown
## Bug Description
Brief description of the issue.

## Environment
- Laravel: 12.0
- PHP: 8.2.0
- Laravel Errly: 1.0.0

## Steps to Reproduce
1. Install Laravel Errly
2. Configure Slack webhook
3. Run `php artisan errly:test`
4. Notice that...

## Expected Behavior
Should send Slack notification with...

## Actual Behavior
Instead, it throws error...

## Additional Context
Any relevant configuration, logs, or screenshots.
```

## ✨ **Feature Requests**

We love feature ideas! When suggesting features:

### **Before Requesting**
- **Check existing issues** to avoid duplicates
- **Consider the scope** - does it fit Laravel Errly's mission?
- **Think about breaking changes** - backward compatibility matters

### **Feature Request Template**
```markdown
## Feature Description
Clear description of the proposed feature.

## Use Case
Why would this feature be useful? What problem does it solve?

## Proposed Implementation
How should this feature work? Any API ideas?

## Alternatives Considered
What other approaches did you consider?

## Additional Context
Screenshots, mockups, or examples.
```

## 🔧 **Development Guidelines**

### **Adding New Features**

1. **Create an issue first** to discuss the feature
2. **Write tests** before implementing
3. **Update documentation** as needed
4. **Consider backward compatibility**
5. **Test with real Laravel app**

### **Adding New Notification Channels**

For Discord, Teams, Email, etc.:

1. **Create new notification class** extending Laravel's `Notification`
2. **Add configuration options** to `config/errly.php`
3. **Update service provider** to handle new channel
4. **Add tests** for the new notification format
5. **Update README** with new channel instructions

Example structure:
```php
// src/Notifications/DiscordErrorNotification.php
class DiscordErrorNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['discord'];
    }
    
    public function toDiscord($notifiable): DiscordMessage
    {
        // Implementation
    }
}
```

### **Configuration Changes**

When modifying `config/errly.php`:

1. **Add environment variable support** - `env('ERRLY_NEW_SETTING')`
2. **Provide sensible defaults** 
3. **Document in README** 
4. **Consider migration path** for existing users
5. **Update tests** that depend on configuration

## 🎯 **Pull Request Process**

### **Before Submitting**

1. **Fork the repository** and create a feature branch
2. **Write tests** for your changes
3. **Ensure all tests pass** locally
4. **Update documentation** if needed
5. **Follow code style guidelines**

### **Pull Request Template**

```markdown
## Description
Brief description of changes made.

## Type of Change
- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added tests for new functionality
- [ ] Tested with real Laravel application

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or clearly documented)
```

### **Review Process**

1. **Automated checks** must pass (tests, code style, static analysis)
2. **Maintainer review** - we'll provide feedback and suggestions
3. **Address feedback** - make requested changes
4. **Final approval** - merge when everything looks good

## 🌟 **Recognition**

Contributors are recognized in:

- **README credits section**
- **Release notes** for significant contributions
- **GitHub contributors graph**
- **Package documentation** for major features

## 📞 **Getting Help**

If you need help contributing:

- **Open a discussion** on GitHub for questions
- **Check existing issues** for similar problems
- **Review the codebase** - the code is well-documented
- **Start small** - fix typos, improve tests, update docs

## 🤝 **Code of Conduct**

### **Our Standards**

- **Be respectful** and inclusive
- **Focus on constructive feedback**
- **Help others learn** and grow
- **Celebrate different perspectives**
- **Prioritize community over individual**

### **Unacceptable Behavior**

- Harassment, discrimination, or offensive language
- Personal attacks or trolling
- Spam or off-topic discussions
- Sharing private information without permission

## 📋 **Development Roadmap**

### **Short-term (v1.x)**
- Discord notification support
- Teams notification support
- Email notification support
- Enhanced error grouping

### **Medium-term (v2.x)**
- Error analytics dashboard
- Custom notification templates
- Webhook integrations
- Performance monitoring

### **Long-term (v3.x)**
- Multi-app support
- Advanced filtering rules
- Error trending analysis
- Integration with monitoring services

## 💝 **Thank You!**

Every contribution, no matter how small, helps make Laravel Errly better for the entire Laravel community. Thank you for taking the time to contribute! 🙏

---

**Questions?** Open a discussion or issue - we're here to help! 💬
