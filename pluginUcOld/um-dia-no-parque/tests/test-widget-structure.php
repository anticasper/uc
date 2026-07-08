<?php
/**
 * Tests for Elementor widget structure.
 *
 * Verifies that widget files exist, classes are defined,
 * and required methods are present.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * Widget structure test case.
 */
class WidgetStructureTest extends UMDNP_UnitTestCase {

    /**
     * Plugin root directory.
     */
    private function plugin_dir(): string {
        return dirname(__DIR__);
    }

    /**
     * Test that the map widget file exists.
     */
    public function test_mapa_widget_file_exists(): void {
        $this->assertFileExists(
            dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php'
        );
    }

    /**
     * Test that the map widget class is defined.
     */
    public function test_mapa_widget_class_exists(): void {
        $file = dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php';
        require_once $file;
        $this->assertTrue(
            class_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo'),
            'Class Um_Dia_No_Parque_Widget_Mapa_Interativo should exist'
        );
    }

    /**
     * Test that the map widget has all required Elementor methods.
     */
    public function test_map_widget_has_required_methods(): void {
        require_once dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php';

        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_name'),
            'Widget must have get_name()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_title'),
            'Widget must have get_title()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_icon'),
            'Widget must have get_icon()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_categories'),
            'Widget must have get_categories()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'register_controls'),
            'Widget must have register_controls()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'render'),
            'Widget must have render()'
        );
    }

    /**
     * Test that the map widget has optional but recommended methods.
     */
    public function test_map_widget_has_optional_methods(): void {
        require_once dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php';

        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_style_depends'),
            'Widget should have get_style_depends()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_script_depends'),
            'Widget should have get_script_depends()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_keywords'),
            'Widget should have get_keywords()'
        );
        $this->assertTrue(
            method_exists('Um_Dia_No_Parque_Widget_Mapa_Interativo', 'get_cache_key'),
            'Widget should have get_cache_key()'
        );
    }

    /**
     * Test that the integration loader file exists.
     */
    public function test_elementor_integration_files_exist(): void {
        $base = dirname(__DIR__) . '/elementor';

        $this->assertFileExists($base . '/class-um-dia-no-parque-elementor.php');
        $this->assertFileExists($base . '/class-um-dia-no-parque-elementor-pro.php');
    }

    /**
     * Test that JS and CSS assets exist.
     */
    public function test_frontend_assets_exist(): void {
        $base = dirname(__DIR__) . '/assets';

        $this->assertFileExists($base . '/js/elementor-widgets.js');
        $this->assertFileExists($base . '/css/elementor-widgets.css');
        $this->assertFileExists($base . '/css/elementor-editor.css');
        $this->assertFileExists($base . '/css/im-dia-no-parque-admin.css');
    }

    /**
     * Test that the map widget extends Elementor Widget_Base.
     */
    public function test_mapa_widget_extends_elementor_base(): void {
        $file = dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php';
        require_once $file;

        $reflection = new ReflectionClass('Um_Dia_No_Parque_Widget_Mapa_Interativo');
        $this->assertTrue(
            $reflection->isSubclassOf('\\Elementor\\Widget_Base'),
            'Class should extend \\Elementor\\Widget_Base'
        );
    }

    /**
     * Test that no PHP syntax errors exist in key files.
     */
    public function test_file_syntax(): void {
        $files = array(
            dirname(__DIR__) . '/um-dia-no-parque.php',
            dirname(__DIR__) . '/includes/class-um-dia-no-parque.php',
            dirname(__DIR__) . '/includes/class-um-dia-no-parque-ajax.php',
            dirname(__DIR__) . '/includes/class-um-dia-no-parque-logger.php',
            dirname(__DIR__) . '/admin/class-um-dia-no-parque-admin-settings.php',
            dirname(__DIR__) . '/elementor/class-um-dia-no-parque-elementor.php',
        );

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $output = shell_exec('php -l ' . escapeshellarg($file) . ' 2>&1');
            $this->assertStringContainsString(
                'No syntax errors detected',
                $output ?? '',
                "Syntax error in $file"
            );
        }
    }
}
