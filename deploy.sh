#!/bin/bash

###############################################################################
# InterSoccer Player Management - Deployment Script
###############################################################################
#
# This script deploys the plugin to the dev server and can run tests.
#
# Usage:
#   ./deploy.sh                 # Deploy to dev server
#   ./deploy.sh --test          # Run tests before deploying
#   ./deploy.sh --no-cache      # Deploy and clear server caches
#   ./deploy.sh --dry-run       # Show what would be uploaded
#
###############################################################################

# Exit on error
set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
# IMPORTANT: Copy this file to deploy.local.sh and set your credentials there
# deploy.local.sh is in .gitignore and won't be committed

# Default configuration (override in deploy.local.sh)
SERVER_USER="your-username"
SERVER_HOST="intersoccer.legit.ninja"
SERVER_PATH="/path/to/wordpress/wp-content/plugins/player-management"
SSH_PORT="22"
SSH_KEY="~/.ssh/id_rsa"

# Cypress test directory (relative to this plugin directory)
CYPRESS_TEST_DIR="../intersoccer-player-management-tests"
CYPRESS_SPEC="cypress/e2e/player_management.spec.js"

# Load local configuration if it exists
if [ -f "deploy.local.sh" ]; then
    source deploy.local.sh
    echo -e "${GREEN}✓ Loaded local configuration${NC}"
fi

# Parse command line arguments
DRY_RUN=false
RUN_TESTS=false
CLEAR_CACHE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --test)
            RUN_TESTS=true
            shift
            ;;
        --no-cache|--clear-cache)
            CLEAR_CACHE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --dry-run        Show what would be uploaded without uploading"
            echo "  --test           Run PHPUnit tests before deploying"
            echo "  --clear-cache    Clear server caches after deployment"
            echo "  --help           Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Check if configuration is set
if [ "$SERVER_USER" = "your-username" ]; then
    echo -e "${RED}✗ Configuration not set!${NC}"
    echo ""
    echo "Please create a deploy.local.sh file with your server credentials:"
    echo ""
    echo "cat > deploy.local.sh << 'EOF'"
    echo "SERVER_USER=\"your-ssh-username\""
    echo "SERVER_HOST=\"intersoccer.legit.ninja\""
    echo "SERVER_PATH=\"/var/www/html/wp-content/plugins/intersoccer-product-variations\""
    echo "SSH_PORT=\"22\""
    echo "SSH_KEY=\"~/.ssh/id_rsa\""
    echo "EOF"
    echo ""
    exit 1
fi

###############################################################################
# Functions
###############################################################################

print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

run_phpunit_tests() {
    print_header "Running PHPUnit Tests"
    
    if [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${YELLOW}⚠ PHPUnit not installed. Skipping PHPUnit tests.${NC}"
        echo "  Run 'composer install' to enable PHPUnit tests."
        return 0
    fi
    
    # Check if WordPress test suite is configured
    if ! grep -q "WP_TESTS_DIR" tests/bootstrap.php 2>/dev/null; then
        echo -e "${YELLOW}⚠ PHPUnit bootstrap not configured. Skipping PHPUnit tests.${NC}"
        echo "  Configure tests/bootstrap.php to enable PHPUnit tests."
        return 0
    fi
    
    # Check if WordPress tests path exists
    WP_TEST_PATH=$(grep "define('WP_TESTS_DIR'" tests/bootstrap.php 2>/dev/null | sed -n "s/.*'\([^']*\)'.*/\1/p")
    if [ ! -z "$WP_TEST_PATH" ] && [ ! -d "$WP_TEST_PATH" ]; then
        echo -e "${YELLOW}⚠ WordPress test suite not found at: $WP_TEST_PATH${NC}"
        echo "  Skipping PHPUnit tests. Configure WP_TESTS_DIR in tests/bootstrap.php."
        return 0
    fi
    
    echo "Running PHPUnit tests..."
    vendor/bin/phpunit
    
    if [ $? -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ All PHPUnit tests passed${NC}"
        return 0
    else
        echo ""
        echo -e "${RED}✗ PHPUnit tests failed${NC}"
        return 1
    fi
}

run_cypress_tests() {
    print_header "Running Cypress Tests"
    
    # Check if Cypress test directory exists
    if [ ! -d "$CYPRESS_TEST_DIR" ]; then
        echo -e "${YELLOW}⚠ Cypress test directory not found: $CYPRESS_TEST_DIR${NC}"
        echo "  Skipping Cypress tests."
        return 0
    fi
    
    # Check if Cypress is installed in test directory
    if [ ! -f "$CYPRESS_TEST_DIR/node_modules/.bin/cypress" ]; then
        echo -e "${YELLOW}⚠ Cypress not installed in $CYPRESS_TEST_DIR${NC}"
        echo "  Run: cd $CYPRESS_TEST_DIR && npm install"
        return 1
    fi
    
    # Save current directory
    ORIGINAL_DIR=$(pwd)
    
    # Change to Cypress test directory
    cd "$CYPRESS_TEST_DIR"
    
    echo "Running Cypress tests from: $(pwd)"
    echo "Spec pattern: $CYPRESS_SPEC"
    echo ""
    
    # Run the specific test spec
    if [ -n "$CYPRESS_SPEC" ]; then
        npx cypress run --spec "$CYPRESS_SPEC" --env environment=dev
    else
        # Run all tests
        npm run test
    fi
    
    CYPRESS_EXIT_CODE=$?
    
    # Return to original directory
    cd "$ORIGINAL_DIR"
    
    if [ $CYPRESS_EXIT_CODE -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ All Cypress tests passed${NC}"
        return 0
    else
        echo ""
        echo -e "${RED}✗ Cypress tests failed${NC}"
        return 1
    fi
}

deploy_to_server() {
    print_header "Deploying to Server"
    
    # Validate SERVER_PATH
    if [ -z "$SERVER_PATH" ]; then
        echo -e "${RED}✗ ERROR: SERVER_PATH is not set!${NC}"
        echo ""
        echo "Please set SERVER_PATH in deploy.local.sh to the FULL PATH of this specific plugin:"
        echo "  SERVER_PATH=\"/var/www/html/wp-content/plugins/player-management\""
        echo ""
        echo "⚠️  DO NOT use the plugins directory path - this would affect other plugins!"
        exit 1
    fi
    
    # Safety check: Ensure path ends with plugin name
    if [[ ! "$SERVER_PATH" =~ player-management/?$ ]]; then
        echo -e "${YELLOW}⚠️  WARNING: SERVER_PATH should end with 'player-management'${NC}"
        echo "Current path: $SERVER_PATH"
        echo ""
        echo "Expected format: /path/to/wp-content/plugins/player-management"
        echo ""
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Deployment cancelled."
            exit 1
        fi
    fi
    
    echo -e "Target: ${GREEN}${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}${NC}"
    echo ""
    
    # Build rsync command WITHOUT --delete flag
    # Using --delete is dangerous - could delete other plugins if path is wrong!
    RSYNC_CMD="rsync -avz"
    
    # Add dry-run flag if requested
    if [ "$DRY_RUN" = true ]; then
        RSYNC_CMD="$RSYNC_CMD --dry-run"
        echo -e "${YELLOW}DRY RUN MODE - No files will be uploaded${NC}"
        echo ""
    fi
    
    # Add SSH options
    RSYNC_CMD="$RSYNC_CMD -e 'ssh -p ${SSH_PORT} -i ${SSH_KEY}'"
    
    # Exclude files/directories
    RSYNC_CMD="$RSYNC_CMD \
        --exclude='.git' \
        --exclude='.gitignore' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='tests' \
        --exclude='cypress' \
        --exclude='.phpunit.result.cache' \
        --exclude='composer.json' \
        --exclude='composer.lock' \
        --exclude='package.json' \
        --exclude='package-lock.json' \
        --exclude='phpunit.xml' \
        --exclude='*.log' \
        --exclude='debug.log' \
        --exclude='*.sh' \
        --exclude='.DS_Store' \
        --exclude='*.swp' \
        --exclude='*~'"
    
    # Add source and destination
    RSYNC_CMD="$RSYNC_CMD ./ ${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}/"
    
    # Execute rsync
    echo "Uploading files..."
    eval $RSYNC_CMD
    
    if [ $? -eq 0 ]; then
        if [ "$DRY_RUN" = false ]; then
            echo ""
            echo -e "${GREEN}✓ Files uploaded successfully${NC}"
        fi
    else
        echo -e "${RED}✗ Upload failed${NC}"
        exit 1
    fi
}

copy_translations_to_global_dir() {
    print_header "Copying Translation Files to Global Directory"
    
    # Create a temporary PHP script to copy .mo files
    COPY_SCRIPT='<?php
// Load WordPress to get constants
define("WP_USE_THEMES", false);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php");

// Copy .mo files from plugin languages/ to wp-content/languages/plugins/
$plugin_lang_dir = dirname(__FILE__) . "/languages/";
$global_lang_dir = WP_CONTENT_DIR . "/languages/plugins/";

// Ensure global directory exists
if (!is_dir($global_lang_dir)) {
    mkdir($global_lang_dir, 0755, true);
    echo "✓ Created global language directory\n";
}

$mo_files = glob($plugin_lang_dir . "*.mo");
$copied = 0;
$failed = 0;

foreach ($mo_files as $source) {
    $filename = basename($source);
    $dest = $global_lang_dir . $filename;
    
    if (copy($source, $dest)) {
        echo "✓ Copied: " . $filename . "\n";
        $copied++;
    } else {
        echo "✗ Failed to copy: " . $filename . "\n";
        $failed++;
    }
}

echo "\nCopied " . $copied . " translation file(s) to global directory.\n";
if ($failed > 0) {
    echo "Failed to copy " . $failed . " file(s).\n";
}
unlink(__FILE__);
?>'
    
    # Upload and execute the script
    echo "$COPY_SCRIPT" | ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cat > ${SERVER_PATH}/copy-translations-temp.php"
    
    echo ""
    echo "Copying translation files on server..."
    ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cd ${SERVER_PATH} && php copy-translations-temp.php"
    
    echo ""
    echo -e "${GREEN}✓ Translation files copied to global directory${NC}"
}

clear_server_caches() {
    print_header "Clearing Server Caches"
    
    # Create a temporary PHP script to clear caches
    CLEAR_SCRIPT='<?php
// Load WordPress to get functions
define("WP_USE_THEMES", false);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php");

// Clear PHP Opcache
if (function_exists("opcache_reset")) {
    opcache_reset();
    echo "✓ PHP Opcache cleared\n";
} else {
    echo "⚠ PHP Opcache not available\n";
}

// Clear WooCommerce transients
if (function_exists("wc_delete_product_transients")) {
    wc_delete_product_transients(0);
    echo "✓ WooCommerce transients cleared\n";
}

// Clear WordPress object cache
if (function_exists("wp_cache_flush")) {
    wp_cache_flush();
    echo "✓ WordPress object cache cleared\n";
}

// Clear translation cache
if (function_exists("delete_transient")) {
    delete_transient("translation_cache");
    echo "✓ Translation cache cleared\n";
}

// Clear WordPress language cache
if (function_exists("wp_cache_delete")) {
    wp_cache_delete("translations", "options");
    echo "✓ Language cache cleared\n";
}

echo "\nCaches cleared successfully!\n";
unlink(__FILE__);
?>'
    
    # Upload and execute the script
    echo "$CLEAR_SCRIPT" | ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cat > ${SERVER_PATH}/clear-cache-temp.php"
    
    echo ""
    echo "Executing cache clear script on server..."
    ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cd ${SERVER_PATH} && php clear-cache-temp.php"
    
    echo ""
    echo -e "${GREEN}✓ Server caches cleared${NC}"
}

###############################################################################
# Main Script
###############################################################################

print_header "InterSoccer Player Management Deployment"

echo "Configuration:"
echo "  Server: ${SERVER_USER}@${SERVER_HOST}"
echo "  Path: ${SERVER_PATH}"
echo "  SSH Port: ${SSH_PORT}"
echo ""

# Run tests if requested
if [ "$RUN_TESTS" = true ]; then
    PHPUNIT_RESULT=0
    CYPRESS_RESULT=0
    
    # Run PHPUnit tests (gracefully skips if not configured)
    run_phpunit_tests
    PHPUNIT_RESULT=$?
    
    # PHPUnit returns 0 if passed or skipped, 1 if actually failed
    if [ $PHPUNIT_RESULT -ne 0 ]; then
        echo -e "${RED}✗ PHPUnit tests failed. Aborting deployment.${NC}"
        exit 1
    fi
    
    # Run Cypress E2E tests
    run_cypress_tests
    CYPRESS_RESULT=$?
    
    if [ $CYPRESS_RESULT -ne 0 ]; then
        echo -e "${RED}✗ Cypress tests failed. Aborting deployment.${NC}"
        exit 1
    fi
    
    echo ""
    echo -e "${GREEN}✓ All configured tests passed${NC}"
    echo ""
fi

# Deploy to server
deploy_to_server

# Copy translations to global directory
if [ "$DRY_RUN" = false ]; then
    copy_translations_to_global_dir
fi

# Clear caches if requested
if [ "$CLEAR_CACHE" = true ] && [ "$DRY_RUN" = false ]; then
    clear_server_caches
fi

# Success message
if [ "$DRY_RUN" = false ]; then
    print_header "Deployment Complete"
    echo -e "${GREEN}✓ Plugin successfully deployed to ${SERVER_HOST}${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Clear browser cache and hard refresh (Ctrl+Shift+R)"
    echo "  2. Test the changes on: https://${SERVER_HOST}/shop/"
    echo "  3. Check browser console for any errors"
    echo ""
else
    echo ""
    echo -e "${YELLOW}DRY RUN completed. No files were uploaded.${NC}"
    echo "Run without --dry-run to actually deploy."
    echo ""
fi

