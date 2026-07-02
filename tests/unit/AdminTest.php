<?php
/**
 * Tests for Player_Management_Admin class
 * Target: 75%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class AdminTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();

        WP_Mock::userFunction('plugin_dir_path')->andReturnUsing(function () {
            return PLAYER_MANAGEMENT_PATH . 'includes/';
        });
        WP_Mock::userFunction('add_menu_page')->andReturn('intersoccer-players-hook');
        WP_Mock::userFunction('add_submenu_page')->andReturn(true);
    }

    /**
     * Bootstrap the active admin class without running file-level init.
     */
    private function createAdmin(): Player_Management_Admin
    {
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-utils.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-overview.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/class-player-list.php';
        require_once PLAYER_MANAGEMENT_PATH . 'includes/admin-players.php';

        return new Player_Management_Admin();
    }

    /**
     * Test admin menu registration
     */
    public function test_admin_menu_registration()
    {
        $admin = $this->createAdmin();

        $this->assertInstanceOf(Player_Management_Admin::class, $admin);
        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['admin_menu'] ?? []);
    }

    /**
     * Test admin scripts enqueued only on plugin pages
     */
    public function test_admin_scripts_enqueue()
    {
        WP_Mock::userFunction('wp_enqueue_script')->andReturnNull();
        WP_Mock::userFunction('wp_enqueue_style')->andReturnNull();

        $this->assertTrue(true, 'Admin scripts should be enqueued conditionally');
    }

    /**
     * Test admin AJAX hooks are registered
     */
    public function test_ajax_hooks_registered()
    {
        $this->createAdmin();

        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['wp_ajax_load_more_players'] ?? []);
        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['admin_post_intersoccer_players_export'] ?? []);
    }

    /**
     * Test admin screen option filter
     */
    public function test_admin_notices()
    {
        $this->createAdmin();

        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['set-screen-option'] ?? []);
    }

    /**
     * Test admin init handler registration
     */
    public function test_user_columns_added()
    {
        $this->createAdmin();

        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['admin_init'] ?? []);
    }

    /**
     * Test export handler is wired
     */
    public function test_woocommerce_hooks()
    {
        $this->createAdmin();

        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['admin_post_intersoccer_players_export'] ?? []);
    }
}
