<?php
/**
 * PHPUnit bootstrap for Um Dia No Parque plugin tests.
 *
 * Two modes:
 *   1. Unit tests (no WordPress) — just run `phpunit`
 *   2. Integration tests (with WordPress) — set WP_TESTS_DIR env or define it below.
 *
 * @package Um_Dia_No_Parque\Tests
 */

// Detect test mode.
$is_integration = !empty(getenv('WP_TESTS_DIR')) || defined('WP_TESTS_DIR');

if ($is_integration) {
    // ==========================================
    // Integration test bootstrap (WordPress)
    // ==========================================

    $wp_tests_dir = getenv('WP_TESTS_DIR') ?: (defined('WP_TESTS_DIR') ? WP_TESTS_DIR : '');
    $wp_tests_dir = rtrim($wp_tests_dir, '/');

    if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
        echo "WordPress test lib not found at: $wp_tests_dir\n";
        echo "Set WP_TESTS_DIR env var or define it in phpunit.xml\n";
        exit(1);
    }

    // Load the plugin main file.
    $_plugin_file = dirname(__DIR__) . '/um-dia-no-parque.php';
    $_tests_dir   = $wp_tests_dir;

    require_once $_tests_dir . '/includes/functions.php';

    /**
     * Load the plugin after WordPress is loaded.
     */
    function _load_um_dia_no_parque(): void {
        require_once dirname(__DIR__) . '/um-dia-no-parque.php';
    }
    tests_add_filter('muplugins_loaded', '_load_um_dia_no_parque');

    require_once $_tests_dir . '/includes/bootstrap.php';

} else {
    // ==========================================
    // Unit test bootstrap (no WordPress)
    // ==========================================

    // Define ABSPATH so our files don't die.
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }

    // Load the plugin's main class (for constants).
    require_once dirname(__DIR__) . '/um-dia-no-parque.php';

    // Load test utilities.
    require_once __DIR__ . '/includes/class-test-case.php';
}
