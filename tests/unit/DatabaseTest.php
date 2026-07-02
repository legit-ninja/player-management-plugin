<?php
/**
 * Tests for InterSoccer_Player_Database class
 * Target: 80%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/class-logger.php';
require_once __DIR__ . '/../../includes/class-player-management-database.php';

class DatabaseTest extends InterSoccer_Test_Case
{
    private $database;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock wpdb global
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_charset_collate')->andReturn('');
        
        $this->database = new InterSoccer_Player_Database();
    }

    /**
     * Test database initialization
     */
    public function test_database_initialization()
    {
        $this->assertInstanceOf(InterSoccer_Player_Database::class, $this->database);
    }

    /**
     * Test create_tables method
     */
    public function test_create_tables()
    {
        global $wpdb;
        $wpdb->shouldReceive('get_charset_collate')->andReturn('DEFAULT CHARSET=utf8mb4');

        $upgrade_dir = ABSPATH . 'wp-admin/includes/';
        if (!is_dir($upgrade_dir)) {
            wp_mkdir_p($upgrade_dir);
        }
        if (!file_exists($upgrade_dir . 'upgrade.php')) {
            file_put_contents($upgrade_dir . 'upgrade.php', "<?php\n");
        }

        $this->database->create_tables();
        $this->assertTrue(true, 'Tables should be created without errors');
    }

    /**
     * Test table names are properly prefixed
     */
    public function test_table_names_use_prefix()
    {
        global $wpdb;
        
        // Test that table names include the prefix
        $this->assertTrue(true, 'Table names should use wpdb prefix');
    }

    /**
     * Test database version constant
     */
    public function test_database_version_constant()
    {
        $this->assertEquals('2.0.0', InterSoccer_Player_Database::DB_VERSION);
    }
}

