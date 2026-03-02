# Changelog

All notable changes to `laravel-errly` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/jeromecoloma/laravel-errly/compare/v1.0.2...HEAD)

## [1.0.2](https://github.com/jeromecoloma/laravel-errly/compare/v1.0.1...v1.0.2) - 2026-03-02

### Changed

- **Updated dependencies** - Ran `composer audit` and `composer update` to resolve security advisories and pull in latest dependency versions
  - `symfony/clock`: v7.4.0 -> v8.0.0
  - `symfony/css-selector`: v7.4.6 -> v8.0.6
  - `symfony/event-dispatcher`: v7.4.4 -> v8.0.4
  - `symfony/string`: v7.4.6 -> v8.0.6
  - `symfony/translation`: v7.4.6 -> v8.0.6
  - `symfony/yaml`: v7.4.1 -> v7.4.6
  - `phpunit/phpunit`: 11.5.15 -> 11.5.50
  - `pestphp/pest`: v3.8.2 -> v3.8.5
  - `nunomaduro/collision`: v8.8.3 -> v8.9.1
  - `phpdocumentor/reflection-docblock`: 5.6.6 -> 6.0.2
  - `phpdocumentor/type-resolver`: 1.12.0 -> 2.0.0
  - `brianium/paratest`: v7.8.3 -> v7.8.5
  - `fidry/cpu-core-counter`: 1.2.0 -> 1.3.0
  - `laravel/pail`: v1.2.4 -> v1.2.6
  - `laravel/tinker`: v2.11.0 -> v2.11.1
  - `psy/psysh`: v0.12.18 -> v0.12.20
  - `phpunit/php-file-iterator`: 5.1.0 -> 5.1.1
  - `sebastian/comparator`: 6.3.2 -> 6.3.3
  - `ta-tikoma/phpunit-architecture-test`: 0.8.5 -> 0.8.7

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

- **Initial release** of Laravel Errly
- **Slack notifications** for Laravel exceptions
- **Smart error filtering** - ignores validation errors, 404s, etc.
- **Severity levels** - CRITICAL, HIGH, MEDIUM error classification
- **Security features** - automatic sensitive data redaction
- **Rate limiting** - prevents notification spam
- **Rich context** - user info, request details, server info, stack traces
- **Testing commands** - `php artisan errly:test` with multiple error types
- **Laravel 12 native** - built for modern Laravel architecture
- **Comprehensive configuration** - 25+ customizable settings
- **Professional documentation** - complete setup and usage guide

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
