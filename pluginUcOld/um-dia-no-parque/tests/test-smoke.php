<?php
/**
 * Smoke test — verifies the plugin loads without fatal errors.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * Smoke test case.
 */
class SmokeTest extends UMDNP_UnitTestCase {

    /**
     * Test that the main plugin file loads without error.
     */
    public function test_plugin_loads(): void {
        // The bootstrap already loads the main plugin file.
        // If we got here, it loaded without fatal errors.
        $this->assertTrue(true);
    }

    /**
     * Test that all required includes exist.
     */
    public function test_required_files_exist(): void {
        $files = array(
            '/um-dia-no-parque.php',
            '/includes/class-um-dia-no-parque.php',
            '/includes/class-um-dia-no-parque-ajax.php',
            '/includes/class-um-dia-no-parque-logger.php',
            '/includes/class-um-dia-no-parque-seo.php',
            '/includes/post-types/class-um-dia-no-parque-post-type-uc.php',
            '/elementor/class-um-dia-no-parque-elementor.php',
            '/elementor/class-um-dia-no-parque-elementor-pro.php',
        );

        foreach ($files as $file) {
            $this->assertFileExists(
                dirname(__DIR__) . $file,
                "Required file missing: $file"
            );
        }
    }

    /**
     * Test that the plugin header is valid.
     */
    public function test_plugin_header(): void {
        $plugin_data = get_file_data(
            dirname(__DIR__) . '/um-dia-no-parque.php',
            array(
                'Version'     => 'Version',
                'Plugin Name' => 'Plugin Name',
                'Text Domain' => 'Text Domain',
            )
        );

        $this->assertEquals('1.5.0', $plugin_data['Version']);
        $this->assertEquals('Um dia No Parque', $plugin_data['Plugin Name']);
        $this->assertEquals('um-dia-no-parque', $plugin_data['Text Domain']);
    }

    /**
     * Test that there are no duplicate Elementor control names.
     */
    public function test_no_duplicate_control_names(): void {
        $widget_file = dirname(__DIR__) . '/elementor/widgets/class-widget-mapa-interativo.php';
        $content     = file_get_contents($widget_file);

        // Find all add_control names.
        preg_match_all("/'([a-z_]+)'.*\n.*'label'/", $content, $matches);
        $names = $matches[1];
        $dupes = array_diff_assoc($names, array_unique($names));

        $this->assertEmpty(
            $dupes,
            'Duplicate control names found: ' . implode(', ', $dupes)
        );
    }

    /**
     * Test that no SWITCH (wrong) constant is used (should be SWITCHER).
     */
    public function test_no_switch_constant(): void {
        $widget_dir = dirname(__DIR__) . '/elementor';

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($widget_dir)
        );

        $found = array();
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if (preg_match('/Controls_Manager::SWITCH[^E]/', $content)) {
                $found[] = $file->getFilename();
            }
        }

        $this->assertEmpty(
            $found,
            'Files using deprecated SWITCH constant: ' . implode(', ', $found)
        );
    }

    /**
     * Test that the textdomain is loaded.
     */
    public function test_textdomain_loaded(): void {
        $this->assertTrue(
            is_textdomain_loaded('um-dia-no-parque'),
            'Textdomain um-dia-no-parque should be loaded'
        );
    }

    /**
     * Test that the post type is registered.
     */
    public function test_uc_post_type_registered(): void {
        $this->assertTrue(
            post_type_exists('uc'),
            'Post type "uc" should be registered'
        );
    }

    /**
     * Test that taxonomies are registered.
     */
    public function test_taxonomies_registered(): void {
        $this->assertTrue(
            taxonomy_exists('categoria_uc'),
            'Taxonomy categoria_uc should be registered'
        );
        $this->assertTrue(
            taxonomy_exists('categoria_uc'),
            'Taxonomy categoria_uc should be registered'
        );
    }
}
