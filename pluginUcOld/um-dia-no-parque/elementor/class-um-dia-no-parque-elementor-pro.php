<?php
/**
 * Elementor Pro Integration
 *
 * Manages Elementor Pro–specific features: Dynamic Tags, Theme Builder Conditions,
 * Form Actions / Fields, and Popup integration.
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
 * Elementor Pro integration class.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_Elementor_Pro {

    /**
     * Singleton instance.
     *
     * @since  1.0.0
     * @access private
     * @var    Um_Dia_No_Parque_Elementor_Pro|null $instance
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return Um_Dia_No_Parque_Elementor_Pro
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
    private function __construct() {}

    /**
     * Initialize all Pro features.
     *
     * @since 1.0.0
     */
    public function init() {
        $this->init_dynamic_tags();
        $this->init_theme_builder_conditions();
        $this->init_form_actions();
        $this->init_popup_integration();
    }

    // ---------------------------------------------------------------
    // 1. Dynamic Tags
    // ---------------------------------------------------------------

    /**
     * Register custom Dynamic Tags for Elementor Pro.
     *
     * @since 1.0.0
     */
    private function init_dynamic_tags() {
        add_action('elementor/dynamic_tags/register', array($this, 'register_dynamic_tags'));
    }

    /**
     * Register all custom dynamic tags.
     *
     * Compatível com Elementor 4.x (base classes em novos namespaces).
     *
     * @since 1.0.0
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
     */
    public function register_dynamic_tags($dynamic_tags_manager) {
        // Register the tag group.
        $dynamic_tags_manager->register_group(
            'um-dia-no-parque',
            array(
                'title' => esc_html__('Um Dia No Parque', 'um-dia-no-parque'),
            )
        );

        // ---------------------------------------------------------
        // 1. UC Meta tag (TEXT_CATEGORY) — exibe metadados da UC
        // ---------------------------------------------------------
        // Guard: only load Pro tag classes if the base class exists.
        if (class_exists('\\ElementorPro\\Modules\\DynamicTags\\Tags\\Base\\Tag')) {
            require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/dynamic-tags/class-tag-uc-meta.php';
            $dynamic_tags_manager->register(new Um_Dia_No_Parque_Tag_UC_Meta());
        }

        // ---------------------------------------------------------
        // 2. Page URL tag (URL_CATEGORY) — links para páginas do plugin
        // ---------------------------------------------------------
        if (class_exists('\\Elementor\\Core\\DynamicTags\\Data_Tag')) {
            require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/dynamic-tags/class-tag-page-url.php';
            $dynamic_tags_manager->register(new Um_Dia_No_Parque_Tag_Page_URL());
        }

        // ---------------------------------------------------------
        // 3. Page Settings tag (TEXT_CATEGORY) — conteúdo das abas
        //    Home, Atividades, Experiências, O Movimento
        // ---------------------------------------------------------
        // Shared trait for tags 3 and 4 (uses Data_Tag guard, available in all Elementor installs).
        if (class_exists('\\Elementor\\Core\\DynamicTags\\Data_Tag')) {
            require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/dynamic-tags/trait-pages-base.php';
        }

        if (class_exists('\\ElementorPro\\Modules\\DynamicTags\\Tags\\Base\\Tag')) {
            require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/dynamic-tags/class-tag-page-settings.php';
            $dynamic_tags_manager->register(new Um_Dia_No_Parque_Tag_Page_Settings());
        }

        // ---------------------------------------------------------
        // 4. Page Image tag (IMAGE_CATEGORY) — imagens das páginas
        //    para uso no widget de Imagem do Elementor
        // ---------------------------------------------------------
        if (class_exists('\\Elementor\\Core\\DynamicTags\\Data_Tag')) {
            require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/dynamic-tags/class-tag-page-image.php';
            $dynamic_tags_manager->register(new Um_Dia_No_Parque_Tag_Page_Image());
        }
    }

    // ---------------------------------------------------------------
    // 2. Theme Builder Conditions
    // ---------------------------------------------------------------

    /**
     * Register custom Theme Builder display conditions.
     *
     * @since 1.0.0
     */
    private function init_theme_builder_conditions() {
        // Not needed — Elementor 4.x natively supports CPT conditions
        // (singular/uc, archive/uc, taxonomy/categoria_uc).
    }

    // ---------------------------------------------------------------
    // 3. Form Actions (Elementor Pro Forms)
    // ---------------------------------------------------------------

    /**
     * Register custom form actions.
     *
     * @since 1.0.0
     */
    private function init_form_actions() {
        add_action('elementor_pro/forms/actions/register', array($this, 'register_form_actions'));
    }

    /**
     * Register the "Salvar Inscrição no Parque" form action.
     *
     * @since 1.0.0
     * @param \ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $form_actions_registrar
     */
    public function register_form_actions($form_actions_registrar) {
        // Guard: only load Pro form action classes if the base class exists.
        if (!class_exists('\\ElementorPro\\Modules\\Forms\\Classes\\Action_Base')) {
            return;
        }

        require_once UM_DIA_NO_PARQUE_PLUGIN_DIR . 'elementor/form-actions/class-form-action-inscricao.php';

        $form_actions_registrar->register(
            new Um_Dia_No_Parque_Form_Action_Inscricao()
        );
    }

    // ---------------------------------------------------------------
    // 4. Popup Integration
    // ---------------------------------------------------------------

    /**
     * Add custom conditions for Elementor Pro Popups.
     *
     * @since 1.4.0
     */
    private function init_popup_integration() {
        // Add popup condition for single UC page.
        add_filter('elementor_pro/popups/display_settings', array($this, 'popup_display_settings'), 10, 2);
    }

    /**
     * Filter popup display settings to include UC-related rules.
     *
     * @since  1.4.0
     * @param  array       $settings Display settings.
     * @param  \WP_Post    $post     Popup post object.
     * @return array
     */
    public function popup_display_settings($settings, $post) {
        // Expose UC meta as available conditions for popups.
        if (is_singular('uc')) {
            $settings['umdnp_uc_id'] = get_the_ID();
        }
        return $settings;
    }
}
