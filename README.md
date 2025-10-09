# InterSoccer Player Management Plugin

## Overview
The InterSoccer Player Management plugin provides comprehensive player registration and management capabilities for InterSoccer Switzerland's sports programs. It enables parents to manage their children's profiles through the WooCommerce My Account interface and provides administrators with powerful tools for player oversight, analytics, and event tracking. The plugin integrates seamlessly with WooCommerce orders and supports advanced features like medical information tracking, event history, and Elementor widget integration.

## Version
- **Current Version:** 1.10.9
- **Release Date:** October 9, 2025

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
- **Excel Export**: Spreadsheet export functionality using PhpSpreadsheet
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
  - PhpSpreadsheet (^1.29) - Excel export functionality
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

## Configuration
- **User Roles**: Custom roles are automatically registered (coach, organizer)
- **My Account Menu**: "Manage Players" endpoint added to WooCommerce account menu
- **Admin Menus**: Player management menu added to WordPress admin
- **Caching**: Overview data cached for 30 minutes with manual refresh option

## Development Workflow
- **Local Development**: Code locally, test on development environment
- **Version Control**: Commit to `github.com/legit-ninja/player-management-plugin`
- **Testing**: PHPUnit unit tests and integration testing
- **Code Quality**: Task runner for linting and automated checks
- **Dependencies**: Composer for PHP dependencies, npm for build processes

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
