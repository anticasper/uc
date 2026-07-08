<?php
/**
 * Tests for settings sanitization.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * Settings test case.
 */
class SettingsTest extends UMDNP_UnitTestCase {

    /**
     * @var Um_Dia_No_Parque_Admin_Settings
     */
    private $settings;

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();

        // Load required files.
        require_once dirname(__DIR__) . '/includes/class-um-dia-no-parque-logger.php';
        require_once dirname(__DIR__) . '/admin/class-um-dia-no-parque-admin-settings.php';

        // Get the singleton instance.
        $this->settings = Um_Dia_No_Parque_Admin_Settings::get_instance();
    }

    /**
     * Test default settings values.
     */
    public function test_default_settings(): void {
        $defaults = $this->settings->get_default_settings();
        $this->assertIsArray($defaults);

        $this->assertArrayHasKey('map_default_lat', $defaults);
        $this->assertArrayHasKey('map_default_lng', $defaults);
        $this->assertArrayHasKey('map_default_zoom', $defaults);
        $this->assertArrayHasKey('parques_per_page', $defaults);
        $this->assertArrayHasKey('enable_leaflet_cdn', $defaults);
        $this->assertArrayHasKey('enable_logging', $defaults);
        $this->assertArrayHasKey('log_level', $defaults);

        $this->assertEquals('-14.2350', $defaults['map_default_lat']);
        $this->assertEquals('-51.9253', $defaults['map_default_lng']);
        $this->assertEquals(4, $defaults['map_default_zoom']);
        $this->assertEquals(12, $defaults['parques_per_page']);
        $this->assertEquals('yes', $defaults['enable_leaflet_cdn']);
        $this->assertEquals('yes', $defaults['enable_logging']);
    }

    /**
     * Test sanitize — map coordinates.
     */
    public function test_sanitize_coordinates(): void {
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => ' -23.5505 ',
            'map_default_lng' => ' -46.6333 ',
            'map_default_zoom' => 10,
            'parques_per_page' => 20,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 1,
        )));

        $this->assertEquals('-23.5505', $sanitized['map_default_lat']);
        $this->assertEquals('-46.6333', $sanitized['map_default_lng']);
        $this->assertEquals(10, $sanitized['map_default_zoom']);
    }

    /**
     * Test sanitize — zoom clamping.
     */
    public function test_sanitize_zoom_clamping(): void {
        // Zoom too high.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 999,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 1,
        )));
        $this->assertLessThanOrEqual(18, $sanitized['map_default_zoom']);

        // Zoom too low.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => -5,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 1,
        )));
        $this->assertGreaterThanOrEqual(1, $sanitized['map_default_zoom']);
    }

    /**
     * Test sanitize — parques per page clamping.
     */
    public function test_sanitize_parques_per_page(): void {
        // Too many.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 999,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 1,
        )));
        $this->assertLessThanOrEqual(100, $sanitized['parques_per_page']);

        // Too few.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 1,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 1,
        )));
        $this->assertGreaterThanOrEqual(3, $sanitized['parques_per_page']);
    }

    /**
     * Test sanitize — switch toggles.
     */
    public function test_sanitize_switches(): void {
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'no',
            'log_level' => 1,
        )));
        $this->assertEquals('yes', $sanitized['enable_leaflet_cdn']);
        $this->assertEquals('no', $sanitized['enable_logging']);

        // Invalid values fall back to 'no'.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'invalid',
            'enable_logging' => 'invalid',
            'log_level' => 1,
        )));
        $this->assertEquals('no', $sanitized['enable_leaflet_cdn']);
        $this->assertEquals('no', $sanitized['enable_logging']);
    }

    /**
     * Test sanitize — log level clamping.
     */
    public function test_sanitize_log_level(): void {
        // Too high.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => 999,
        )));
        $this->assertLessThanOrEqual(4, $sanitized['log_level']);

        // Too low.
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '0',
            'map_default_lng' => '0',
            'map_default_zoom' => 4,
            'parques_per_page' => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging' => 'yes',
            'log_level' => -1,
        )));
        $this->assertGreaterThanOrEqual(0, $sanitized['log_level']);
    }

    /**
     * Test that empty input fills defaults.
     */
    public function test_sanitize_empty_input(): void {
        $sanitized = $this->call_private_method($this->settings, 'sanitize_settings', array(array(
            'map_default_lat' => '',
            'map_default_lng' => '',
            'map_default_zoom' => '',
            'parques_per_page' => '',
            'enable_leaflet_cdn' => '',
            'enable_logging' => '',
            'log_level' => '',
        )));

        $defaults = $this->settings->get_default_settings();
        $this->assertEquals($defaults['map_default_lat'], $sanitized['map_default_lat']);
        $this->assertEquals($defaults['map_default_lng'], $sanitized['map_default_lng']);
        $this->assertEquals($defaults['map_default_zoom'], $sanitized['map_default_zoom']);
        $this->assertEquals($defaults['parques_per_page'], $sanitized['parques_per_page']);
    }
}
