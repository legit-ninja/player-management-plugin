# InterSoccer Player Management Plugin

## Overview
The InterSoccer Player Management plugin provides comprehensive player registration and management capabilities for InterSoccer Switzerland's sports programs. It enables parents to manage their children's profiles through the WooCommerce My Account interface and provides administrators with powerful tools for player oversight, analytics, and event tracking. The plugin integrates seamlessly with WooCommerce orders and supports advanced features like medical information tracking, event history, and Elementor widget integration.

## Version
- **Current Version:** 2.10.5.1
- **Release Date:** September 28, 2026

## Core Features

### Player Profile Management
- **Parent Interface**: User-friendly "Manage Players" section in WooCommerce My Account
- **Comprehensive Data**: Stores player information including name, date of birth, gender, AVS number, medical conditions, and dietary requirements
- **Dynamic Forms**: AJAX-powered add/edit/delete functionality with real-time validation
- **Event Tracking**: Automatic counting and display of player's event participation history
- **Medical & Safety**: Dedicated fields for medical conditions and emergency contact information

### Administrative Dashboard
- **Player Overview**: Comprehensive analytics dashboard with Chart.js visualizations
- **Player List Management**: Advanced table interface with filtering, sorting, and bulk operations
- **User Profile Integration**: Admin access to edit player data directly from user profiles
- **Caching System**: Performance-optimized data caching with manual refresh capabilities
- **Role-Based Access**: Support for custom roles (coach, organizer) with appropriate permissions

### WooCommerce Integration
- **Order Integration**: Automatic player assignment to WooCommerce orders
- **Event Counting**: Real-time calculation of player event participation
- **Cart Assignment**: Dynamic player selection during checkout process
- **Order Metadata**: Player information attached to order records for roster generation

### Advanced Features
- **Elementor Widgets**: Custom widgets for player lists and management interfaces
- **Background Processing**: Asynchronous operations using WP Background Processing
- **PDF Generation**: Player data export capabilities using DomPDF
- **CSV Export**: Formula-safe CSV export functionality (Excel/XLSX exports live in the sibling plugin [`intersoccer-reports-rosters`](https://github.com/legit-ninja/reports-rosters); this plugin ships CSV only)
- **Data Validation**: Comprehensive input validation and sanitization

### Database Architecture
- **User Meta Storage**: Player data stored in WordPress user meta tables
- **Future Gamification**: Prepared database tables for event tracking and points systems
- **Indexing**: Optimized database queries with proper indexing
- **Migration Support**: Version-based database upgrades and data migration

## Technical Architecture

### AJAX-Powered Interface
- **Real-time Updates**: Asynchronous player management without page refreshes
- **Security**: Nonce-based request validation and user permission checks
- **Error Handling**: Comprehensive error logging and user feedback
- **Performance**: Optimized queries and caching for large player datasets

### User Roles & Permissions
- **Administrator**: Full access to all player data and administrative functions
- **Coach**: Read-only access to player information and basic management
- **Organizer**: Event organization permissions with player assignment capabilities
- **Customer/Parent**: Access to manage their own children's profiles

### Integration Points
- **WooCommerce**: Seamless integration with orders, cart, and checkout
- **Elementor**: Widget support for page builders
- **WordPress Users**: Leverages WordPress user system for authentication
- **Reports & Rosters**: Data synchronization with roster management system

## Dependencies
- **Required Plugins**:
  - WooCommerce (core e-commerce functionality)
- **PHP Libraries**:
  - DomPDF (^2.0) - PDF generation
  - WP Background Processing (^1.0) - Asynchronous processing
- **JavaScript Libraries**:
  - Chart.js (3.9.1) - Analytics visualizations
  - Flatpickr (4.6.13) - Date picker functionality

## Installation & Setup
1. Upload plugin files to `/wp-content/plugins/player-management/`
2. Activate through WordPress admin
3. Ensure WooCommerce is installed and active
4. Plugin automatically creates required database tables
5. Configure user roles and permissions as needed

## Development Workflow
- **Local Development**: Code locally, test on development environment
- **Version Control**: Commit to `github.com/legit-ninja/player-management-plugin`
- **Testing**: PHPUnit unit tests and integration testing
- **Code Quality**: Task runner for linting and automated checks
- **Dependencies**: Composer for PHP dependencies, npm for build processes
- **Underdog publish**: Release tags publish a plugin ZIP via GitHub Actions — see [docs/underdog-publish.md](docs/underdog-publish.md) (tags, secrets, staging vs prod, WP Admin update smoke test)

## Pre-commit checks

A local pre-commit hook (`bin/pre-commit`) checks staged changes before each commit. It normally finishes in a few seconds.

What it checks:

- `php -l` on staged `.php` files (the staged version).
- PHPCS on staged `.php` files with the repo's phpcs config. If `vendor/bin/phpcs` or a phpcs config is missing (true today), this step is skipped with a warning.
- A fast PHPUnit subset: the `unit` testsuite (`tests/unit`, WP_Mock only, no WordPress or database). It runs only when PHP, `phpunit.xml` or Composer files are staged, and it tests the working tree.
- Added lines must not contain internal hostnames (`*.local`, `*.lan`, `*.internal` and similar, or host names containing `staging`), IPv4 addresses (loopback, all-zero addresses and version numbers are allowed), private keys, or new `error_log(`, `var_dump(` or `print_r(` calls in PHP files. The author and committer email must not use a local domain.
- No staged deletions under `vendor/`.

Install once per clone (this covers every worktree of the clone):

```bash
bin/install-hooks.sh              # or: composer install-hooks
bin/install-hooks.sh --uninstall  # remove it again
```

PHP: the hook uses `php` from your PATH. If there is none, it runs PHP in the `php:8.x-cli` Docker image named at the top of `bin/pre-commit` (pull it first). PHPUnit needs `vendor/bin/phpunit`. In a worktree without its own `vendor/`, point the hook at one (mounted read-only, Docker runner only):

```bash
git config intersoccer.hooks.vendorDir /path/to/a/vendor   # or INTERSOCCER_HOOK_VENDOR=... for one run
```

Set `INTERSOCCER_HOOK_SKIP_PHPUNIT=1` to skip only the tests.

Local deny-list: list real internal hostnames, one per line, in `~/.config/intersoccer-hooks/denylist` or `.git/hooks-denylist`. Both are outside the tracked tree. Never commit their contents.

Bypass: `git commit --no-verify` skips every check for that one commit. Use it sparingly, for example for an intentional `vendor/` update.

## Configuration
- **User Roles**: Custom roles are automatically registered (coach, organizer)
- **My Account Menu**: "Manage Players" endpoint added to WooCommerce account menu
- **Admin Menus**: Player management menu added to WordPress admin
- **Caching**: Overview data cached for 30 minutes with manual refresh option
- **Underdog Updates**: Players → Settings (Update Stream + License) with active `intersoccer-updates` — see [docs/underdog-publish.md](docs/underdog-publish.md)

## Key Metrics & Monitoring
- Player registration completion rates
- Event participation tracking accuracy
- Admin dashboard performance
- AJAX response times and error rates

## Future Enhancements
- Gamification system with points and achievements
- Advanced reporting and analytics
- Mobile app integration
- API endpoints for external systems
- Enhanced parent communication features

## Troubleshooting
- **Debug Mode**: Enable `WP_DEBUG` for detailed error logging
- **AJAX Issues**: Check nonce validation and user permissions
- **Performance**: Monitor database queries and caching effectiveness
- **Integration**: Verify WooCommerce hooks and order processing

## Security Features
- **Input Sanitization**: All user inputs validated and sanitized
- **Permission Checks**: Role-based access control throughout
- **Nonce Validation**: CSRF protection on all AJAX requests
- **Data Validation**: Comprehensive validation of player data fields

## License
GPL-2.0-or-later - See LICENSE file for details.

## Contributors
- Jeremy Lee (Lead Developer)
