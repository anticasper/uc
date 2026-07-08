<?php
/**
 * Elementor Integration — Main Class
 *
 * Manages all integration between the plugin and Elementor (free + Pro).
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Elementor integration class.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_Elementor {

    /**
     * Instance of this class (singleton).
     *
     * @since  1.0.0
     * @access private
     * @var    Um_Dia_No_Parque_Elementor|null $instance
     */
    private static $instance = null;

    /**
     * Whether Elementor Pro is active.
     *
     * @since  1.0.0
     * @access private
     * @var    bool $pro_active
     */
    private $pro_active = false;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return Um_Dia_No_Parque_Elementor
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton instance.
     *
     * @throws \Exception Always throws.
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the Elementor integration.
     *
     * @since 1.0.0
     */
    public function init() {
        // Only run if Elementor (free) is active.
        if (!did_action('elementor/loaded')) {
            return;
        }

        $this->pro_active = self::is_elementor_pro_active();

        // Register widget category.
        add_action('elementor/elements/categories_registered', array($this, 'add_widget_category'));

        // Register widgets.
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Register frontend CSS.
        add_action('elementor/frontend/after_enqueue_styles', array($this, 'enqueue_frontend_styles'));

        // Register frontend JS.
        add_action('elementor/frontend/after_register_scripts', array($this, 'register_frontend_scripts'));
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // Register Leaflet assets for the interactive map widget.
        add_action('wp_enqueue_scripts', array($this, 'register_leaflet_assets'), 5);

        // Register widget styles (for editor preview).
        add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueue_editor_styles'));

        // --- Elementor Pro features ---
        if ($this->pro_active) {
            $this->init_pro_features();
        }
    }

    /**
     * Initialize Elementor Pro–specific features.
     *
     * @since 1.0.0
     */
    private function init_pro_features() {
        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/class-um-dia-no-parque-elementor-pro.php';
        Um_Dia_No_Parque_Elementor_Pro::get_instance()->init();
    }

    /**
     * Register the custom widget category.
     *
     * @since 1.0.0
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function add_widget_category($elements_manager) {
        $elements_manager->add_category(
            'um-dia-no-parque',
            array(
                'title' => esc_html__('Um Dia No Parque', 'um-dia-no-parque'),
                'icon'  => 'fa fa-tree',
            )
        );
    }

    /**
     * Register all custom widgets.
     *
     * @since 1.0.0
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager) {
        // Include widget base class and individual widgets.
        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/widgets/class-widget-base.php';
        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/widgets/class-widget-mapa-interativo.php';
        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/widgets/class-widget-explorar.php';
        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/widgets/class-widget-atividades-carousel.php';

        // Register widgets.
        $widgets_manager->register(new Um_Dia_No_Parque_Widget_Mapa_Interativo());
        $widgets_manager->register(new Um_Dia_No_Parque_Widget_Explorar());
        $widgets_manager->register(new Um_Dia_No_Parque_Widget_Atividades_Carousel());
    }

    /**
     * Enqueue frontend styles for Elementor widgets.
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'um-dia-no-parque-elementor-widgets',
            UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/elementor-widgets.css',
            array(),
            UM_DIA_NO_PARQUE_VERSION
        );
    }

    /**
     * Register frontend scripts.
     *
     * @since 1.0.0
     */
    public function register_frontend_scripts() {
        wp_register_script(
            'um-dia-no-parque-elementor-widgets',
            UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/js/elementor-widgets.js',
            array('jquery', 'elementor-frontend'),
            UM_DIA_NO_PARQUE_VERSION,
            true
        );

        // Localize for AJAX.
        wp_localize_script(
            'um-dia-no-parque-elementor-widgets',
            'umdnp_elementor',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('um_dia_no_parque_elementor_nonce'),
                'i18n'     => array(
                    'error'   => esc_html__('Ocorreu um erro. Tente novamente.', 'um-dia-no-parque'),
                    'loading' => esc_html__('Carregando...', 'um-dia-no-parque'),
                    'no_results' => esc_html__('Nenhum resultado encontrado.', 'um-dia-no-parque'),
                ),
            )
        );
    }

    /**
     * Enqueue frontend scripts.
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('um-dia-no-parque-elementor-widgets');
    }

    /**
     * Enqueue editor styles for Elementor preview.
     *
     * @since 1.0.0
     */
    public function enqueue_editor_styles() {
        wp_enqueue_style(
            'um-dia-no-parque-elementor-editor',
            UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/elementor-editor.css',
            array(),
            UM_DIA_NO_PARQUE_VERSION
        );
    }

    /**
     * Register Leaflet CSS and JS — CDN or local fallback.
     *
     * Checks the plugin setting 'enable_leaflet_cdn' to decide
     * whether to load from CDN or from the plugin's local assets.
     *
     * @since 1.0.0
     */
    public function register_leaflet_assets() {
        $settings = get_option('um_dia_no_parque_settings', array());
        $use_cdn  = !isset($settings['enable_leaflet_cdn']) || 'yes' === $settings['enable_leaflet_cdn'];

        if ($use_cdn) {
            // Leaflet via CDN (default).
            wp_register_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
            wp_register_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
            wp_register_style('leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array('leaflet'), '1.5.3');
            wp_register_style('leaflet-cluster-default', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array('leaflet-cluster'), '1.5.3');
            wp_register_script('leaflet-cluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array('leaflet'), '1.5.3', true);
        } else {
            // Leaflet from local assets (offline-friendly).
            $local_url = UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/lib/leaflet/';
            wp_register_style('leaflet', $local_url . 'leaflet.css', array(), '1.9.4');
            wp_register_script('leaflet', $local_url . 'leaflet.js', array(), '1.9.4', true);
            wp_register_style('leaflet-cluster', $local_url . 'MarkerCluster.css', array('leaflet'), '1.5.3');
            wp_register_style('leaflet-cluster-default', $local_url . 'MarkerCluster.Default.css', array('leaflet-cluster'), '1.5.3');
            wp_register_script('leaflet-cluster', $local_url . 'leaflet.markercluster.js', array('leaflet'), '1.5.3', true);
        }

    }

    /**
     * Check if Elementor Pro is active.
     *
     * @since  1.0.0
     * @return bool
     */
    public static function is_elementor_pro_active() {
        return defined('ELEMENTOR_PRO_VERSION');
    }
}
