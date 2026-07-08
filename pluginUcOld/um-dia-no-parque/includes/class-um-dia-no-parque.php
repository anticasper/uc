<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Core plugin class.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */
class Um_Dia_No_Parque {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if (defined('UM_DIA_NO_PARQUE_VERSION')) {
			$this->version = UM_DIA_NO_PARQUE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'um-dia-no-parque';

		add_action('init', array($this, 'init_plugin'), 0);

		// Elementor hooks must be registered BEFORE init priority 0,
		// because Elementor fires elementor/widgets/register during its own
		// init at priority 0. If we wait until init_plugin(), we miss the hook.
		if (did_action('elementor/loaded') || defined('ELEMENTOR_VERSION')) {
			Um_Dia_No_Parque_Elementor::get_instance();
		}
	}

	/**
	 * Initialize the plugin on 'init' hook (WP 6.7+ best practice).
	 *
	 * @since 1.6.0
	 */
	public function init_plugin() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Instantiate developer info (registers its own hooks internally).
		new Um_Dia_No_Parque_Developer_Info();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
	    // All classes are autoloaded via spl_autoload_register
	    // (defined in the main plugin file). We only need to
	    // instantiate the singletons to register their hooks.

	    // --- Custom Post Types ---
	    Um_Dia_No_Parque_Post_Type_UC::get_instance();
	    Um_Dia_No_Parque_Post_Type_Atividades::get_instance();
	    Um_Dia_No_Parque_Post_Type_Depoimentos::get_instance();
	    Um_Dia_No_Parque_Post_Type_Parceiros::get_instance();
	    Um_Dia_No_Parque_Post_Type_UFs::get_instance();
	    Um_Dia_No_Parque_Post_Type_OQue_Levar::get_instance();

	    // --- Migration (data repair for UF-Cidade linkage) ---
	    Um_Dia_No_Parque_Migration::get_instance();

	    // --- Services ---
	    Um_Dia_No_Parque_AJAX::get_instance();
	    Um_Dia_No_Parque_SEO::get_instance();
	    Um_Dia_No_Parque_Admin_Settings::get_instance();
	    Um_Dia_No_Parque_Logger::get_instance();
	    Um_Dia_No_Parque_Import::get_instance();

	    // --- Elementor (already initialized in constructor to beat race condition) ---
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_admin_styles() {
		wp_enqueue_style(
			$this->plugin_name . '-admin',
			UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/im-dia-no-parque-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_script(
			$this->plugin_name . '-admin',
			UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/js/im-dia-no-parque-admin.js',
			array('jquery'),
			$this->version,
			false
		);
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_public_styles'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_public_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/im-dia-no-parque-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_public_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/js/im-dia-no-parque-public.js',
			array('jquery'),
			$this->version,
			false
		);
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
