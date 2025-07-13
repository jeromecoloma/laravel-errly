# Changelog

All notable changes to `laravel-errly` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/jeromecoloma/laravel-errly/compare/v1.0.1...HEAD)

## [1.0.1](https://github.com/jeromecoloma/laravel-errly/releases/tag/v1.0.1) - 2025-07-13

### Changed

- **Enhanced package metadata** - Improved composer.json with better description and comprehensive keywords
- **Expanded package keywords** - Added `laravel-12`, `exception-handler`, `free-alternative`, `bugsnag-alternative`
- **Updated package description** - Changed from "Early error detection" to "Error monitoring with beautiful Slack notifications"
- **Added funding support** - GitHub Sponsors integration for project sustainability
- **Enhanced support links** - Added comprehensive support URLs (issues, source, docs)
- **Improved build scripts** - Added `test-all` composer script for comprehensive testing workflow

### Fixed

- **CI/CD improvements** - Removed Windows testing from GitHub Actions for better reliability
- **Workflow optimization** - Focused on Linux environment testing (ubuntu-latest only)
- **Increased timeout** - Extended dependency resolution timeout from 5 to 10 minutes
- **Reduced job complexity** - Streamlined from 8 to 4 jobs for faster execution

## [1.0.0](https://github.com/jeromecoloma/laravel-errly/releases/tag/v1.0.0) - 2025-07-11

### Added

- 🚨 **Initial release** of Laravel Errly
- ⚡ **Slack notifications** for Laravel exceptions
- 🛡️ **Smart error filtering** - ignores validation errors, 404s, etc.
- 🎯 **Severity levels** - CRITICAL, HIGH, MEDIUM error classification
- 🔒 **Security features** - automatic sensitive data redaction
- ⚙️ **Rate limiting** - prevents notification spam
- 🎨 **Rich context** - user info, request details, server info, stack traces
- 🧪 **Testing commands** - `php artisan errly:test` with multiple error types
- 📱 **Laravel 12 native** - built for modern Laravel architecture
- 🔧 **Comprehensive configuration** - 25+ customizable settings
- 📖 **Professional documentation** - complete setup and usage guide

### Configuration Options

- Environment filtering (production/staging only)
- Custom ignored and critical exception lists
- Slack channel, username, and emoji customization
- Context collection settings (user, request, headers, stack traces)
- Rate limiting with configurable thresholds
- Sensitive field redaction

### Testing Features

- Multiple error type testing (`general`, `critical`, `database`, `validation`, `custom`)
- Validation error filtering verification
- Manual error reporting via `Errly::report()` facade
- Comprehensive error context collection

### Security Features

- Automatic password and token redaction
- Safe header filtering (removes authorization headers)
- Configurable sensitive field protection
- Input sanitization for Slack notifications

### Laravel 12 Integration

- Bootstrap exception handler integration
- Service provider auto-discovery
- Artisan command registration
- Configuration publishing support
