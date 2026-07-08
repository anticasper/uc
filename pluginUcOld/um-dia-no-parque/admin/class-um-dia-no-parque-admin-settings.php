<?php
/**
 * Admin Settings Page
 *
 * Provides the admin settings page with configuration, cache management,
 * and system information for the Um Dia No Parque plugin.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/admin
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings class.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_Admin_Settings {

    /**
     * The plugin option name.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $option_name = 'um_dia_no_parque_settings';

    /**
     * The plugin slug.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $plugin_slug = 'um-dia-no-parque';

    /**
     * The plugin name.
     *
     * @since  1.0.0
     * @access private
     * @var    string
     */
    private $plugin_name = 'Um Dia No Parque';

    /**
     * The pages option name.
     *
     * @since  1.7.0
     * @access private
     * @var    string
     */
    private $pages_option_name = 'um_dia_no_parque_pages';

    /**
     * Instance of this class (singleton).
     *
     * @since  1.0.0
     * @access private
     * @var    Um_Dia_No_Parque_Admin_Settings|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.0.0
     * @return Um_Dia_No_Parque_Admin_Settings
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(UM_DIA_NO_PARQUE_PLUGIN_DIR . $this->plugin_slug . '.php'), array($this, 'add_action_links'));
        add_action('wp_ajax_umdnp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_umdnp_reset_all', array($this, 'ajax_reset_all'));
    }

    /**
     * Add admin menu pages.
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Top-level menu page.
        add_menu_page(
            $this->plugin_name,
            $this->plugin_name,
            'manage_options',
            $this->plugin_slug,
            array($this, 'render_settings_page'),
            'dashicons-palmtree',
            30
        );

        // Settings sub-page.
        add_submenu_page(
            $this->plugin_slug,
            esc_html__('Configurações', 'um-dia-no-parque'),
            esc_html__('Configurações', 'um-dia-no-parque'),
            'manage_options',
            $this->plugin_slug,
            array($this, 'render_settings_page')
        );

        // Pages sub-page.
        add_submenu_page(
            $this->plugin_slug,
            esc_html__('Páginas', 'um-dia-no-parque'),
            esc_html__('Páginas', 'um-dia-no-parque'),
            'manage_options',
            $this->plugin_slug . '-pages',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings via the Settings API.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            $this->option_name . '_general',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => $this->get_default_settings(),
            )
        );

        // General section.
        add_settings_section(
            'umdnp_general_section',
            esc_html__('Configurações Gerais', 'um-dia-no-parque'),
            array($this, 'render_general_section_callback'),
            $this->option_name . '_general'
        );

        add_settings_field(
            'map_default_lat',
            esc_html__('Latitude Padrão do Mapa', 'um-dia-no-parque'),
            array($this, 'render_text_field'),
            $this->option_name . '_general',
            'umdnp_general_section',
            array(
                'name'        => 'map_default_lat',
                'default'     => '-14.2350',
                'description' => esc_html__('Latitude central usada como padrão nos mapas.', 'um-dia-no-parque'),
            )
        );

        add_settings_field(
            'map_default_lng',
            esc_html__('Longitude Padrão do Mapa', 'um-dia-no-parque'),
            array($this, 'render_text_field'),
            $this->option_name . '_general',
            'umdnp_general_section',
            array(
                'name'        => 'map_default_lng',
                'default'     => '-51.9253',
                'description' => esc_html__('Longitude central usada como padrão nos mapas.', 'um-dia-no-parque'),
            )
        );

        add_settings_field(
            'map_default_zoom',
            esc_html__('Zoom Padrão do Mapa', 'um-dia-no-parque'),
            array($this, 'render_number_field'),
            $this->option_name . '_general',
            'umdnp_general_section',
            array(
                'name'        => 'map_default_zoom',
                'default'     => 4,
                'min'         => 1,
                'max'         => 18,
                'description' => esc_html__('Nível de zoom padrão (1 a 18).', 'um-dia-no-parque'),
            )
        );

        add_settings_field(
            'parques_per_page',
            esc_html__('Parques por Página', 'um-dia-no-parque'),
            array($this, 'render_number_field'),
            $this->option_name . '_general',
            'umdnp_general_section',
            array(
                'name'        => 'parques_per_page',
                'default'     => 12,
                'min'         => 3,
                'max'         => 100,
                'description' => esc_html__('Quantidade de parques exibidos por página nos arquivos e widgets de lista.', 'um-dia-no-parque'),
            )
        );

        add_settings_field(
            'enable_leaflet_cdn',
            esc_html__('Carregar Leaflet via CDN', 'um-dia-no-parque'),
            array($this, 'render_switch_field'),
            $this->option_name . '_general',
            'umdnp_general_section',
            array(
                'name'        => 'enable_leaflet_cdn',
                'default'     => 'yes',
                'label'       => esc_html__('Carregar Leaflet.js e CSS de CDN', 'um-dia-no-parque'),
                'description' => esc_html__('Se desativado, usa os arquivos locais do plugin.', 'um-dia-no-parque'),
            )
        );

        // Logger section.
        add_settings_section(
            'umdnp_logger_section',
            esc_html__('Sistema de Log', 'um-dia-no-parque'),
            array($this, 'render_logger_section_callback'),
            $this->option_name . '_general'
        );

        add_settings_field(
            'enable_logging',
            esc_html__('Ativar Log', 'um-dia-no-parque'),
            array($this, 'render_switch_field'),
            $this->option_name . '_general',
            'umdnp_logger_section',
            array(
                'name'        => 'enable_logging',
                'default'     => 'yes',
                'label'       => esc_html__('Manter log de eventos do plugin', 'um-dia-no-parque'),
                'description' => esc_html__('Registra eventos como requisições AJAX, erros de mapa e operações de cache.', 'um-dia-no-parque'),
            )
        );

        add_settings_field(
            'log_level',
            esc_html__('Nível Mínimo de Log', 'um-dia-no-parque'),
            array($this, 'render_select_field'),
            $this->option_name . '_general',
            'umdnp_logger_section',
            array(
                'name'        => 'log_level',
                'default'     => Um_Dia_No_Parque_Logger::INFO,
                'options'     => array(
                    Um_Dia_No_Parque_Logger::DEBUG   => 'DEBUG',
                    Um_Dia_No_Parque_Logger::INFO    => 'INFO',
                    Um_Dia_No_Parque_Logger::WARNING => 'WARNING',
                    Um_Dia_No_Parque_Logger::ERROR   => 'ERROR',
                ),
                'description' => esc_html__('Apenas mensagens deste nível ou superior serão registradas.', 'um-dia-no-parque'),
            )
        );

        // ============================================================
        // Cache Tools section (not saved as settings, uses AJAX)
        // ============================================================
        add_settings_section(
            'umdnp_cache_section',
            esc_html__('Ferramentas de Cache', 'um-dia-no-parque'),
            array($this, 'render_cache_section_callback'),
            $this->option_name . '_cache'
        );

        // ============================================================
        // Pages settings — all tabs share the same option_name
        // ============================================================
        register_setting(
            $this->pages_option_name,
            $this->pages_option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_pages_settings'),
                'default'           => $this->get_default_pages_settings(),
            )
        );

        // --- Home tab sections (page slug: pages_option_name . '_home') ---
        $home_page = $this->pages_option_name . '_home';

        add_settings_section(
            'umdnp_home_banner_slide_section',
            esc_html__('Banner Slide', 'um-dia-no-parque'),
            array($this, 'render_home_banner_slide_section_callback'),
            $home_page
        );
        add_settings_field('home_banner_slide_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_title', 'default' => '', 'description' => esc_html__('Título do banner principal.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_subtitle', esc_html__('Subtítulo', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_subtitle', 'default' => '', 'description' => esc_html__('Texto de apoio no banner.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_btn1_text', esc_html__('Botão 01 — Texto', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_btn1_text', 'default' => '', 'description' => esc_html__('Texto do primeiro botão.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_btn1_url', esc_html__('Botão 01 — Link', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_btn1_url', 'default' => '', 'description' => esc_html__('URL do primeiro botão.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_btn2_text', esc_html__('Botão 02 — Texto', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_btn2_text', 'default' => '', 'description' => esc_html__('Texto do segundo botão.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_btn2_url', esc_html__('Botão 02 — Link', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_btn2_url', 'default' => '', 'description' => esc_html__('URL do segundo botão.', 'um-dia-no-parque')));
        add_settings_field('home_banner_slide_gallery', esc_html__('Galeria de Imagens', 'um-dia-no-parque'), array($this, 'render_pages_gallery_field'), $home_page, 'umdnp_home_banner_slide_section', array('name' => 'home_banner_slide_gallery', 'default' => '', 'description' => esc_html__('Selecione múltiplas imagens para o banner/slide.', 'um-dia-no-parque')));

        add_settings_section(
            'umdnp_home_o_que_e_section',
            esc_html__('O que é', 'um-dia-no-parque'),
            array($this, 'render_home_o_que_e_section_callback'),
            $home_page
        );
        add_settings_field('home_o_que_e_img1', esc_html__('Imagem 1', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_img1', 'default' => '', 'description' => esc_html__('Primeira imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_img2', esc_html__('Imagem 2', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_img2', 'default' => '', 'description' => esc_html__('Segunda imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_img3', esc_html__('Imagem 3', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_img3', 'default' => '', 'description' => esc_html__('Terceira imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_title', 'default' => '', 'description' => esc_html__('Título da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_subtitle', esc_html__('Subtítulo', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_subtitle', 'default' => '', 'description' => esc_html__('Subtítulo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_text', esc_html__('Texto', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_text', 'default' => '', 'description' => esc_html__('Conteúdo descritivo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_btn_text', esc_html__('Botão — Texto', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_btn_text', 'default' => '', 'description' => esc_html__('Texto do botão.', 'um-dia-no-parque')));
        add_settings_field('home_o_que_e_btn_url', esc_html__('Botão — Link', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_o_que_e_section', array('name' => 'home_o_que_e_btn_url', 'default' => '', 'description' => esc_html__('URL do botão.', 'um-dia-no-parque')));

        add_settings_section(
            'umdnp_home_maior_movimento_section',
            esc_html__('O Maior Movimento', 'um-dia-no-parque'),
            array($this, 'render_home_maior_movimento_section_callback'),
            $home_page
        );
        add_settings_field('home_maior_movimento_img1', esc_html__('Imagem 1', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_img1', 'default' => '', 'description' => esc_html__('Primeira imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_img2', esc_html__('Imagem 2', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_img2', 'default' => '', 'description' => esc_html__('Segunda imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_img3', esc_html__('Imagem 3', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_img3', 'default' => '', 'description' => esc_html__('Terceira imagem da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_title', 'default' => '', 'description' => esc_html__('Título da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_subtitle', esc_html__('Subtítulo', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_subtitle', 'default' => '', 'description' => esc_html__('Subtítulo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_text', esc_html__('Texto', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_text', 'default' => '', 'description' => esc_html__('Conteúdo descritivo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_btn_text', esc_html__('Botão — Texto', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_btn_text', 'default' => '', 'description' => esc_html__('Texto do botão.', 'um-dia-no-parque')));
        add_settings_field('home_maior_movimento_btn_url', esc_html__('Botão — Link', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_maior_movimento_section', array('name' => 'home_maior_movimento_btn_url', 'default' => '', 'description' => esc_html__('URL do botão.', 'um-dia-no-parque')));

        add_settings_section(
            'umdnp_home_experiencia_section',
            esc_html__('Experiência', 'um-dia-no-parque'),
            array($this, 'render_home_experiencia_section_callback'),
            $home_page
        );
        add_settings_field('home_experiencia_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_experiencia_section', array('name' => 'home_experiencia_title', 'default' => '', 'description' => esc_html__('Título da seção.', 'um-dia-no-parque')));
        add_settings_field('home_experiencia_description', esc_html__('Descrição', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_experiencia_section', array('name' => 'home_experiencia_description', 'default' => '', 'description' => esc_html__('Conteúdo descritivo.', 'um-dia-no-parque')));

        add_settings_section(
            'umdnp_home_cta_section',
            esc_html__('CTA', 'um-dia-no-parque'),
            array($this, 'render_home_cta_section_callback'),
            $home_page
        );
        add_settings_field('home_cta_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_cta_section', array('name' => 'home_cta_title', 'default' => '', 'description' => esc_html__('Título da chamada para ação.', 'um-dia-no-parque')));
        add_settings_field('home_cta_description', esc_html__('Descrição', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_cta_section', array('name' => 'home_cta_description', 'default' => '', 'description' => esc_html__('Texto descritivo do CTA.', 'um-dia-no-parque')));
        add_settings_field('home_cta_btn_text', esc_html__('Botão — Texto', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_cta_section', array('name' => 'home_cta_btn_text', 'default' => '', 'description' => esc_html__('Texto do botão.', 'um-dia-no-parque')));
        add_settings_field('home_cta_btn_url', esc_html__('Botão — Link', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_cta_section', array('name' => 'home_cta_btn_url', 'default' => '', 'description' => esc_html__('URL do botão.', 'um-dia-no-parque')));
        add_settings_field('home_cta_image', esc_html__('Imagem', 'um-dia-no-parque'), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_cta_section', array('name' => 'home_cta_image', 'default' => '', 'description' => esc_html__('Imagem do CTA.', 'um-dia-no-parque')));

        add_settings_section(
            'umdnp_home_como_participar_section',
            esc_html__('Como Participar', 'um-dia-no-parque'),
            array($this, 'render_home_como_participar_section_callback'),
            $home_page
        );
        add_settings_field('home_como_participar_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => 'home_como_participar_title', 'default' => '', 'description' => esc_html__('Título da seção.', 'um-dia-no-parque')));
        add_settings_field('home_como_participar_subtitle', esc_html__('Subtítulo', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => 'home_como_participar_subtitle', 'default' => '', 'description' => esc_html__('Subtítulo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_como_participar_descricao', esc_html__('Descrição', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => 'home_como_participar_descricao', 'default' => '', 'description' => esc_html__('Texto descritivo da seção.', 'um-dia-no-parque')));
        for ($i = 1; $i <= 4; $i++) {
            /* translators: %d: card number */
            $label = sprintf(__('Card %d', 'um-dia-no-parque'), $i);
            add_settings_field("home_como_participar_card{$i}_icone", sprintf(esc_html__('%s — Ícone', 'um-dia-no-parque'), $label), array($this, 'render_pages_image_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => "home_como_participar_card{$i}_icone", 'default' => '', 'description' => sprintf(esc_html__('Ícone do %s.', 'um-dia-no-parque'), $label)));
            add_settings_field("home_como_participar_card{$i}_titulo", sprintf(esc_html__('%s — Título', 'um-dia-no-parque'), $label), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => "home_como_participar_card{$i}_titulo", 'default' => '', 'description' => sprintf(esc_html__('Título do %s.', 'um-dia-no-parque'), $label)));
            add_settings_field("home_como_participar_card{$i}_subtitulo", sprintf(esc_html__('%s — Subtítulo', 'um-dia-no-parque'), $label), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_como_participar_section', array('name' => "home_como_participar_card{$i}_subtitulo", 'default' => '', 'description' => sprintf(esc_html__('Subtítulo do %s.', 'um-dia-no-parque'), $label)));
        }

        add_settings_section(
            'umdnp_home_compartilhe_section',
            esc_html__('Compartilhe', 'um-dia-no-parque'),
            array($this, 'render_home_compartilhe_section_callback'),
            $home_page
        );
        add_settings_field('home_compartilhe_title', esc_html__('Título', 'um-dia-no-parque'), array($this, 'render_pages_text_field'), $home_page, 'umdnp_home_compartilhe_section', array('name' => 'home_compartilhe_title', 'default' => '', 'description' => esc_html__('Título da seção.', 'um-dia-no-parque')));
        add_settings_field('home_compartilhe_subtitle', esc_html__('Subtítulo', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_compartilhe_section', array('name' => 'home_compartilhe_subtitle', 'default' => '', 'description' => esc_html__('Subtítulo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_compartilhe_descricao', esc_html__('Descrição', 'um-dia-no-parque'), array($this, 'render_pages_editor_field'), $home_page, 'umdnp_home_compartilhe_section', array('name' => 'home_compartilhe_descricao', 'default' => '', 'description' => esc_html__('Texto descritivo da seção.', 'um-dia-no-parque')));
        add_settings_field('home_compartilhe_videos', esc_html__('Vídeos (YouTube)', 'um-dia-no-parque'), array($this, 'render_pages_textarea_field'), $home_page, 'umdnp_home_compartilhe_section', array('name' => 'home_compartilhe_videos', 'default' => '', 'description' => esc_html__('URLs dos vídeos do YouTube, uma por linha.', 'um-dia-no-parque')));

        // --- Atividades tab ---
        $atividades_page = $this->pages_option_name . '_atividades';
        add_settings_section(
            'umdnp_atividades_page_section',
            esc_html__('Página de Atividades', 'um-dia-no-parque'),
            array($this, 'render_atividades_section_callback'),
            $atividades_page
        );
        $this->add_pages_fields('atividades', 'umdnp_atividades_page_section', __('página de atividades', 'um-dia-no-parque'), $atividades_page);

        // --- Experiências tab ---
        $experiencias_page = $this->pages_option_name . '_experiencias';
        add_settings_section(
            'umdnp_experiencias_page_section',
            esc_html__('Página de Experiências', 'um-dia-no-parque'),
            array($this, 'render_experiencias_section_callback'),
            $experiencias_page
        );
        $this->add_pages_fields('experiencias', 'umdnp_experiencias_page_section', __('página de experiências', 'um-dia-no-parque'), $experiencias_page);

        // --- O Movimento tab ---
        $movimento_page = $this->pages_option_name . '_movimento';
        add_settings_section(
            'umdnp_movimento_page_section',
            esc_html__('Página O Movimento', 'um-dia-no-parque'),
            array($this, 'render_movimento_section_callback'),
            $movimento_page
        );
        $this->add_pages_fields('movimento', 'umdnp_movimento_page_section', __('página O Movimento', 'um-dia-no-parque'), $movimento_page);
    }

    /**
     * Get default settings.
     *
     * @since  1.0.0
     * @return array
     */
    public function get_default_settings() {
        return array(
            'map_default_lat'    => '-14.2350',
            'map_default_lng'    => '-51.9253',
            'map_default_zoom'   => 4,
            'parques_per_page'   => 12,
            'enable_leaflet_cdn' => 'yes',
            'enable_logging'     => 'yes',
            'log_level'          => Um_Dia_No_Parque_Logger::INFO,
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @since  1.0.0
     * @param  array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize_settings($input) {
        $defaults = $this->get_default_settings();
        $output   = array();

        $output['map_default_lat'] = isset($input['map_default_lat'])
            ? sanitize_text_field($input['map_default_lat'])
            : $defaults['map_default_lat'];

        $output['map_default_lng'] = isset($input['map_default_lng'])
            ? sanitize_text_field($input['map_default_lng'])
            : $defaults['map_default_lng'];

        $output['map_default_zoom'] = isset($input['map_default_zoom'])
            ? absint($input['map_default_zoom'])
            : $defaults['map_default_zoom'];
        $output['map_default_zoom'] = min(18, max(1, $output['map_default_zoom']));

        $output['parques_per_page'] = isset($input['parques_per_page'])
            ? absint($input['parques_per_page'])
            : $defaults['parques_per_page'];
        $output['parques_per_page'] = min(100, max(3, $output['parques_per_page']));

        $output['enable_leaflet_cdn'] = isset($input['enable_leaflet_cdn']) && 'yes' === $input['enable_leaflet_cdn']
            ? 'yes'
            : 'no';

        $output['enable_logging'] = isset($input['enable_logging']) && 'yes' === $input['enable_logging']
            ? 'yes'
            : 'no';

        $output['log_level'] = isset($input['log_level'])
            ? absint($input['log_level'])
            : $defaults['log_level'];
        $output['log_level'] = min(4, max(0, $output['log_level']));

        // Add a transient notice.
        add_settings_error(
            'umdnp_messages',
            'umdnp_settings_saved',
            esc_html__('Configurações salvas.', 'um-dia-no-parque'),
            'updated'
        );

        return $output;
    }

    // ================================================================
    // Render Methods
    // ================================================================

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Acesso negado.', 'um-dia-no-parque'));
        }

        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : $this->plugin_slug;
        $is_pages_page = ($current_page === $this->plugin_slug . '-pages');

        // Pages submenu — tabs: Home, Atividades, Experiências, O Movimento.
        if ($is_pages_page) {
            $pages_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'home';
            $pages_page_slugs = array(
                'home'         => $this->pages_option_name . '_home',
                'atividades'   => $this->pages_option_name . '_atividades',
                'experiencias' => $this->pages_option_name . '_experiencias',
                'movimento'    => $this->pages_option_name . '_movimento',
            );
            $page_slug = isset($pages_page_slugs[$pages_tab]) ? $pages_page_slugs[$pages_tab] : $pages_page_slugs['home'];
            ?>
            <div class="wrap umdnp-settings-wrap">
                <h1><?php echo esc_html($this->plugin_name); ?> — <?php esc_html_e('Páginas', 'um-dia-no-parque'); ?></h1>

                <nav class="nav-tab-wrapper">
                    <a href="?page=<?php echo esc_attr($this->plugin_slug . '-pages'); ?>&tab=home"
                       class="nav-tab <?php echo 'home' === $pages_tab ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-home"></span>
                        <?php esc_html_e('Home', 'um-dia-no-parque'); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr($this->plugin_slug . '-pages'); ?>&tab=atividades"
                       class="nav-tab <?php echo 'atividades' === $pages_tab ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-games"></span>
                        <?php esc_html_e('Atividades', 'um-dia-no-parque'); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr($this->plugin_slug . '-pages'); ?>&tab=experiencias"
                       class="nav-tab <?php echo 'experiencias' === $pages_tab ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Experiências', 'um-dia-no-parque'); ?>
                    </a>
                    <a href="?page=<?php echo esc_attr($this->plugin_slug . '-pages'); ?>&tab=movimento"
                       class="nav-tab <?php echo 'movimento' === $pages_tab ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e('O Movimento', 'um-dia-no-parque'); ?>
                    </a>
                </nav>

                <div class="umdnp-pages-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->pages_option_name);
                        do_settings_sections($page_slug);
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
            <?php
            return;
        }

        // Settings submenu — tabs: Configurações, Cache, Logs.
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $section    = ('cache' === $active_tab) ? $this->option_name . '_cache' : $this->option_name . '_general';
        ?>
        <div class="wrap umdnp-settings-wrap">
            <h1><?php echo esc_html($this->plugin_name); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>&tab=general"
                   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Configurações', 'um-dia-no-parque'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>&tab=cache"
                   class="nav-tab <?php echo 'cache' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Cache', 'um-dia-no-parque'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>&tab=logs"
                   class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('Logs', 'um-dia-no-parque'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>&tab=import"
                   class="nav-tab <?php echo 'import' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Importar', 'um-dia-no-parque'); ?>
                </a>
                <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>&tab=reset"
                   class="nav-tab <?php echo 'reset' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Redefinir', 'um-dia-no-parque'); ?>
                </a>
            </nav>

            <div class="umdnp-settings-content">
                <?php if ('cache' === $active_tab) : ?>
                    <?php $this->render_cache_tab(); ?>
                <?php elseif ('logs' === $active_tab) : ?>
                    <?php $this->render_logs_tab(); ?>
                <?php elseif ('import' === $active_tab) : ?>
                    <?php $this->render_import_tab(); ?>
                <?php elseif ('reset' === $active_tab) : ?>
                    <?php $this->render_reset_tab(); ?>
                <?php else : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields($this->option_name . '_general');
                        do_settings_sections($section);
                        submit_button();
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the logger section description.
     *
     * @since 1.0.0
     */
    public function render_logger_section_callback() {
        $log_size = Um_Dia_No_Parque_Logger::get_file_size();
        $log_count = Um_Dia_No_Parque_Logger::get_entry_count();
        echo '<p class="description">' . esc_html__('Configurações do sistema de log do plugin.', 'um-dia-no-parque')
            . ' <strong>' . esc_html__('Tamanho atual:', 'um-dia-no-parque') . '</strong> ' . esc_html($log_size)
            . ' | <strong>' . esc_html__('Eventos:', 'um-dia-no-parque') . '</strong> ' . esc_html(number_format_i18n($log_count))
            . '</p>';
    }

    /**
     * Render the logs viewer tab.
     *
     * @since 1.0.0
     */
    private function render_logs_tab() {
        $nonce       = wp_create_nonce('umdnp_clear_cache_nonce');
        $log_size    = Um_Dia_No_Parque_Logger::get_file_size();
        $log_count   = Um_Dia_No_Parque_Logger::get_entry_count();
        $min_level   = isset($_GET['log_level']) ? absint($_GET['log_level']) : 0;
        $search      = isset($_GET['log_search']) ? sanitize_text_field(wp_unslash($_GET['log_search'])) : '';
        $entries     = Um_Dia_No_Parque_Logger::get_entries($min_level, 200, 0, $search);
        ?>
        <div class="umdnp-logs-tab">
            <div class="umdnp-logs-header">
                <p class="description">
                    <?php esc_html_e('Visualize os eventos registrados pelo plugin.', 'um-dia-no-parque'); ?>
                    <strong><?php esc_html_e('Tamanho:', 'um-dia-no-parque'); ?></strong> <?php echo esc_html($log_size); ?>
                    | <strong><?php esc_html_e('Eventos:', 'um-dia-no-parque'); ?></strong> <?php echo esc_html(number_format_i18n($log_count)); ?>
                </p>

                <div class="umdnp-logs-toolbar">
                    <form method="get" action="" class="umdnp-logs-filter">
                        <input type="hidden" name="page" value="<?php echo esc_attr($this->plugin_slug); ?>">
                        <input type="hidden" name="tab" value="logs">

                        <select name="log_level">
                            <option value="0" <?php selected($min_level, 0); ?>><?php esc_html_e('Todos os níveis', 'um-dia-no-parque'); ?></option>
                            <option value="<?php echo esc_attr(Um_Dia_No_Parque_Logger::DEBUG); ?>" <?php selected($min_level, Um_Dia_No_Parque_Logger::DEBUG); ?>>DEBUG</option>
                            <option value="<?php echo esc_attr(Um_Dia_No_Parque_Logger::INFO); ?>" <?php selected($min_level, Um_Dia_No_Parque_Logger::INFO); ?>>INFO</option>
                            <option value="<?php echo esc_attr(Um_Dia_No_Parque_Logger::WARNING); ?>" <?php selected($min_level, Um_Dia_No_Parque_Logger::WARNING); ?>>WARNING</option>
                            <option value="<?php echo esc_attr(Um_Dia_No_Parque_Logger::ERROR); ?>" <?php selected($min_level, Um_Dia_No_Parque_Logger::ERROR); ?>>ERROR</option>
                        </select>

                        <input type="text" name="log_search" value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php esc_attr_e('Buscar nos logs...', 'um-dia-no-parque'); ?>">

                        <button type="submit" class="button">
                            <span class="dashicons dashicons-search"></span> <?php esc_html_e('Filtrar', 'um-dia-no-parque'); ?>
                        </button>

                        <button type="button" class="button umdnp-clear-logs-btn"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Limpar Logs', 'um-dia-no-parque'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div id="umdnp-logs-message" class="notice" style="display:none;"></div>

            <div class="umdnp-logs-table-wrap">
                <?php if (empty($entries)) : ?>
                    <p class="umdnp-logs-empty"><?php esc_html_e('Nenhum evento registrado.', 'um-dia-no-parque'); ?></p>
                <?php else : ?>
                    <table class="widefat striped umdnp-logs-table">
                        <thead>
                            <tr>
                                <th class="umdnp-log-col-time"><?php esc_html_e('Data/Hora', 'um-dia-no-parque'); ?></th>
                                <th class="umdnp-log-col-level"><?php esc_html_e('Nível', 'um-dia-no-parque'); ?></th>
                                <th class="umdnp-log-col-msg"><?php esc_html_e('Mensagem', 'um-dia-no-parque'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry) : ?>
                                <tr class="umdnp-log-row umdnp-log-row-<?php echo esc_attr(strtolower($entry['level'])); ?>">
                                    <td class="umdnp-log-col-time">
                                        <?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($entry['timestamp']))); ?>
                                    </td>
                                    <td class="umdnp-log-col-level">
                                        <span class="umdnp-log-badge umdnp-log-badge-<?php echo esc_attr(strtolower($entry['level'])); ?>">
                                            <?php echo esc_html($entry['level']); ?>
                                        </span>
                                    </td>
                                    <td class="umdnp-log-col-msg">
                                        <?php echo esc_html($entry['message']); ?>
                                        <?php if (!empty($entry['context'])) : ?>
                                            <button type="button" class="umdnp-log-context-toggle"
                                                    title="<?php esc_attr_e('Ver contexto', 'um-dia-no-parque'); ?>">
                                                <span class="dashicons dashicons-info-outline"></span>
                                            </button>
                                            <pre class="umdnp-log-context" style="display:none;"><?php echo esc_html(wp_json_encode($entry['context'], JSON_PRETTY_PRINT)); ?></pre>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle context JSON.
            $('.umdnp-log-context-toggle').on('click', function() {
                $(this).closest('td').find('.umdnp-log-context').slideToggle(150);
                $(this).toggleClass('active');
            });

            // Clear logs.
            $('.umdnp-clear-logs-btn').on('click', function() {
                var $btn = $(this);
                if (!confirm('<?php echo esc_js(__('Tem certeza? Todos os logs serão removidos permanentemente.', 'um-dia-no-parque')); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Limpando...', 'um-dia-no-parque')); ?>');

                $.post(ajaxurl, {
                    action: 'umdnp_clear_cache',
                    cache_type: 'logs',
                    nonce: $btn.data('nonce')
                })
                .done(function(response) {
                    var $msg = $('#umdnp-logs-message');
                    $msg.show();
                    if (response.success) {
                        $msg.removeClass('notice-error').addClass('notice-success');
                        $msg.html('<p>' + response.data.message + '</p>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.removeClass('notice-success').addClass('notice-error');
                        $msg.html('<p>' + (response.data ? response.data.message : '<?php echo esc_js(__('Erro.', 'um-dia-no-parque')); ?>') + '</p>');
                    }
                })
                .always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php echo esc_js(__('Limpar Logs', 'um-dia-no-parque')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render the cache management tab.
     *
     * @since 1.0.0
     */
    private function render_cache_tab() {
        $nonce = wp_create_nonce('umdnp_clear_cache_nonce');
        ?>
        <div class="umdnp-cache-tools">
            <p class="description">
                <?php esc_html_e('Utilize as ferramentas abaixo para limpar caches relacionados ao plugin.', 'um-dia-no-parque'); ?>
            </p>

            <div class="umdnp-cache-cards">
                <?php
                $this->render_cache_card(
                    'transients',
                    esc_html__('Transientes do Plugin', 'um-dia-no-parque'),
                    esc_html__('Remove todos os transientes criados pelo plugin, como dados temporários e consultas em cache.', 'um-dia-no-parque'),
                    'dashicons-clock',
                    esc_html__('Limpar Transientes', 'um-dia-no-parque')
                );

                $this->render_cache_card(
                    'elementor_css',
                    esc_html__('Cache CSS do Elementor', 'um-dia-no-parque'),
                    esc_html__('Limpa o cache de CSS do Elementor, forçando a regeneração dos estilos.', 'um-dia-no-parque'),
                    'dashicons-welcome-widgets-menus',
                    esc_html__('Limpar CSS do Elementor', 'um-dia-no-parque')
                );

                $this->render_cache_card(
                    'rewrite_rules',
                    esc_html__('Regras de Rewrite', 'um-dia-no-parque'),
                    esc_html__('Flush as regras de rewrite do WordPress. Útil após alterar slugs de CPT ou taxonomias.', 'um-dia-no-parque'),
                    'dashicons-admin-links',
                    esc_html__('Flush Rewrite Rules', 'um-dia-no-parque')
                );

                $this->render_cache_card(
                    'all',
                    esc_html__('Limpar Tudo', 'um-dia-no-parque'),
                    esc_html__('Executa todas as limpezas de cache acima de uma só vez.', 'um-dia-no-parque'),
                    'dashicons-trash',
                    esc_html__('Limpar Todos os Caches', 'um-dia-no-parque'),
                    'umdnp-cache-card-danger'
                );
                // Georreferenciamento de UCs
                $this->render_cache_card(
                    'geocode_ucs',
                    esc_html__('Georreferenciar UCs', 'um-dia-no-parque'),
                    esc_html__('Busca coordenadas geográficas (lat/lng) para todas as UCs sem cache. Respeita o limite de 1 requisição/segundo do Nominatim.', 'um-dia-no-parque'),
                    'dashicons-location-alt',
                    esc_html__('Georreferenciar UCs', 'um-dia-no-parque')
                );

                // Estados e Cidades
                $this->render_cache_card(
                    'seed_cidades',
                    esc_html__('Estados e Cidades', 'um-dia-no-parque'),
                    esc_html__('Atualiza os dados de estados (UF) e municípios a partir da API do IBGE. Recria termos sem duplicar existentes.', 'um-dia-no-parque'),
                    'dashicons-admin-site-alt3',
                    esc_html__('Atualizar Estados e Cidades', 'um-dia-no-parque')
                );
                ?>
            </div>

            <div id="umdnp-cache-message" class="notice" style="display:none;"></div>

            <script>
            jQuery(document).ready(function($) {
                $('.umdnp-clear-cache-btn').on('click', function() {
                    var $btn = $(this);
                    var type = $btn.data('cache-type');
                    var originalText = $btn.html();

                    $btn.prop('disabled', true).html(
                        '<span class="dashicons dashicons-update spinning"></span> <?php echo esc_js(__('Processando...', 'um-dia-no-parque')); ?>'
                    );

                    $.post(ajaxurl, {
                        action: 'umdnp_clear_cache',
                        cache_type: type,
                        nonce: '<?php echo esc_js($nonce); ?>'
                    })
                    .done(function(response) {
                        var $msg = $('#umdnp-cache-message');
                        $msg.show();

                        if (response.success) {
                            $msg.removeClass('notice-error').addClass('notice-success');
                            $msg.html('<p>' + response.data.message + '</p>');
                        } else {
                            $msg.removeClass('notice-success').addClass('notice-error');
                            $msg.html('<p>' + (response.data ? response.data.message : '<?php echo esc_js(__('Erro ao limpar cache.', 'um-dia-no-parque')); ?>') + '</p>');
                        }

                        // Auto-hide after 5 seconds.
                        setTimeout(function() {
                            $msg.fadeOut(300);
                        }, 5000);
                    })
                    .fail(function() {
                        var $msg = $('#umdnp-cache-message');
                        $msg.show().removeClass('notice-success').addClass('notice-error');
                        $msg.html('<p><?php echo esc_js(__('Erro na requisição. Tente novamente.', 'um-dia-no-parque')); ?></p>');
                    })
                    .always(function() {
                        $btn.prop('disabled', false).html(originalText);
                    });
                });

                // Georreferenciamento progressivo de UCs.
                $('.umdnp-clear-cache-btn[data-cache-type="geocode_ucs"]').on('click', function() {
                    var $btn = $(this);
                    var originalText = $btn.html();

                    $btn.prop('disabled', true).html(
                        '<span class="dashicons dashicons-update spinning"></span> ' + '<?php echo esc_js(__('Buscando coordenadas...', 'um-dia-no-parque')); ?>'
                    );

                    var $msg = $('#umdnp-cache-message');
                    $msg.show().removeClass('notice-error notice-success').addClass('notice-info');
                    $msg.html('<p><?php echo esc_js(__('Preparando lista de UCs...', 'um-dia-no-parque')); ?></p>');

                    // First, fetch all UC IDs from the map endpoint.
                    $.get(ajaxurl, { action: 'umdnp_get_parques_mapa' }, function(resp) {
                        if (!resp.success || !resp.data.markers || !resp.data.markers.length) {
                            $msg.removeClass('notice-info').addClass('notice-warning');
                            $msg.html('<p><?php echo esc_js(__('Nenhuma UC encontrada para georreferenciar.', 'um-dia-no-parque')); ?></p>');
                            $btn.prop('disabled', false).html(originalText);
                            return;
                        }

                        var ucs = resp.data.markers;
                        var total = ucs.length;
                        var done = 0;
                        var success = 0;
                        var failed = 0;

                        function geocodeNext() {
                            if (done >= total) {
                                $msg.removeClass('notice-info').addClass(success > 0 ? 'notice-success' : 'notice-warning');
                                $msg.html('<p><?php echo esc_js(__('Concluído!', 'um-dia-no-parque')); ?> ' + success + ' OK, ' + failed + ' falhas.</p>');
                                $btn.prop('disabled', false).html(originalText);
                                return;
                            }

                            var uc = ucs[done];
                            // Skip UCs that already have coordinates (not the fallback).
                            if (uc.lat !== -14.235 && uc.lng !== -51.9253) {
                                done++;
                                geocodeNext();
                                return;
                            }

                            $msg.html('<p><?php echo esc_js(__('Georreferenciando', 'um-dia-no-parque')); ?> ' + (done + 1) + '/' + total + ': ' + uc.name.substring(0, 40) + '...</p>');

                            $.post(ajaxurl, {
                                action: 'umdnp_geocode_ucs',
                                nonce: '<?php echo esc_js(wp_create_nonce('um_dia_no_parque_elementor_nonce')); ?>',
                                uc_id: uc.id
                            }, function(resp) {
                                done++;
                                if (resp.success && resp.data.coords) {
                                    success++;
                                } else {
                                    failed++;
                                }
                                // Schedule next with 1.5s delay (Nominatim rate limit).
                                setTimeout(geocodeNext, 1500);
                            }).fail(function() {
                                done++;
                                failed++;
                                setTimeout(geocodeNext, 1500);
                            });
                        }

                        geocodeNext();
                    }).fail(function() {
                        $msg.removeClass('notice-info').addClass('notice-error');
                        $msg.html('<p><?php echo esc_js(__('Erro ao buscar lista de UCs.', 'um-dia-no-parque')); ?></p>');
                        $btn.prop('disabled', false).html(originalText);
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render a cache tool card.
     *
     * @since  1.0.0
     * @param  string $type      Cache type identifier.
     * @param  string $title     Card title.
     * @param  string $desc      Card description.
     * @param  string $dashicon  Dashicon class.
     * @param  string $btn_text  Button text.
     * @param  string $extra_cls Extra CSS class.
     */
    private function render_cache_card($type, $title, $desc, $dashicon, $btn_text, $extra_cls = '') {
        ?>
        <div class="umdnp-cache-card <?php echo esc_attr($extra_cls); ?>">
            <div class="umdnp-cache-card-icon">
                <span class="dashicons <?php echo esc_attr($dashicon); ?>"></span>
            </div>
            <div class="umdnp-cache-card-content">
                <h3><?php echo esc_html($title); ?></h3>
                <p><?php echo esc_html($desc); ?></p>
            </div>
            <div class="umdnp-cache-card-action">
                <button type="button" class="button button-secondary umdnp-clear-cache-btn"
                        data-cache-type="<?php echo esc_attr($type); ?>">
                    <?php echo esc_html($btn_text); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render the system info page.
     *
     * @since 1.0.0
     */
    // ================================================================
    // Section Callbacks
    // ================================================================

    /**
     * Render general section description.
     *
     * @since 1.0.0
     */
    public function render_general_section_callback() {
        echo '<p class="description">' . esc_html__('Configurações gerais do plugin Um Dia No Parque.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render cache section description.
     *
     * @since 1.0.0
     */
    public function render_cache_section_callback() {
        // The cache section is rendered in the tab, not here.
    }

    // ================================================================
    // Field Renderers
    // ================================================================

    /**
     * Render a text input field.
     *
     * @since 1.0.0
     * @param array $args Field arguments.
     */
    public function render_text_field($args, $option_name = null, $defaults_callback = 'get_default_settings') {
        if (null === $option_name) {
            $option_name = $this->option_name;
        }
        $options = get_option($option_name, $this->$defaults_callback());
        $name    = $args['name'];
        $value   = isset($options[$name]) ? $options[$name] : $args['default'];
        ?>
        <input type="text"
               name="<?php echo esc_attr($option_name . '[' . $name . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @since 1.0.0
     * @param array $args Field arguments.
     */
    public function render_number_field($args) {
        $options = get_option($this->option_name, $this->get_default_settings());
        $name    = $args['name'];
        $value   = isset($options[$name]) ? $options[$name] : $args['default'];
        $min     = isset($args['min']) ? $args['min'] : 0;
        $max     = isset($args['max']) ? $args['max'] : 9999;
        ?>
        <input type="number"
               name="<?php echo esc_attr($this->option_name . '[' . $name . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($min); ?>"
               max="<?php echo esc_attr($max); ?>"
               class="small-text">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a switch (checkbox) field.
     *
     * @since 1.0.0
     * @param array $args Field arguments.
     */
    public function render_switch_field($args) {
        $options = get_option($this->option_name, $this->get_default_settings());
        $name    = $args['name'];
        $checked = isset($options[$name]) ? $options[$name] : $args['default'];
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr($this->option_name . '[' . $name . ']'); ?>"
                   value="yes"
                   <?php checked($checked, 'yes'); ?>>
            <?php echo esc_html($args['label']); ?>
        </label>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a select (dropdown) field.
     *
     * @since 1.0.0
     * @param array $args Field arguments.
     */
    public function render_select_field($args) {
        $options = get_option($this->option_name, $this->get_default_settings());
        $name    = $args['name'];
        $value   = isset($options[$name]) ? $options[$name] : $args['default'];
        $choices = $args['options'];
        ?>
        <select name="<?php echo esc_attr($this->option_name . '[' . $name . ']'); ?>">
            <?php foreach ($choices as $opt_value => $opt_label) : ?>
                <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                    <?php echo esc_html($opt_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    // ================================================================
    // Pages Tab
    // ================================================================

    /**
     * Add the 6 standard page fields (title, subtitle, description, hero, CTA text/url).
     *
     * @since  1.7.0
     * @param  string $prefix      Option key prefix (e.g. 'atividades').
     * @param  string $section_id  Settings section ID.
     * @param  string $context     Human-readable context for descriptions.
     * @param  string $page        Settings page slug (default: pages_option_name).
     */
    private function add_pages_fields($prefix, $section_id, $context, $page = null) {
        if (null === $page) {
            $page = $this->pages_option_name;
        }
        $labels = array(
            'title'       => __('Título', 'um-dia-no-parque'),
            'subtitle'    => __('Subtítulo', 'um-dia-no-parque'),
            'description' => __('Descrição', 'um-dia-no-parque'),
            'hero_image'  => __('Imagem de Destaque', 'um-dia-no-parque'),
            'cta_text'    => __('Texto do Botão (CTA)', 'um-dia-no-parque'),
            'cta_url'     => __('Link do Botão (CTA)', 'um-dia-no-parque'),
        );
        $descriptions = array(
            'title'       => sprintf(__('Título exibido no topo da %s.', 'um-dia-no-parque'), $context),
            'subtitle'    => sprintf(__('Texto de apoio abaixo do título da %s.', 'um-dia-no-parque'), $context),
            'description' => sprintf(__('Texto descritivo da %s.', 'um-dia-no-parque'), $context),
            'hero_image'  => sprintf(__('Imagem principal do banner/hero da %s.', 'um-dia-no-parque'), $context),
            'cta_text'    => sprintf(__('Texto do botão de chamada para ação da %s.', 'um-dia-no-parque'), $context),
            'cta_url'     => sprintf(__('URL de destino do botão da %s.', 'um-dia-no-parque'), $context),
        );
        $renderers = array(
            'title'       => 'render_pages_text_field',
            'subtitle'    => 'render_pages_textarea_field',
            'description' => 'render_pages_editor_field',
            'hero_image'  => 'render_pages_image_field',
            'cta_text'    => 'render_pages_text_field',
            'cta_url'     => 'render_pages_text_field',
        );

        foreach ($labels as $key => $label) {
            add_settings_field(
                "{$prefix}_{$key}",
                $label,
                array($this, $renderers[$key]),
                $page,
                $section_id,
                array(
                    'name'        => "{$prefix}_{$key}",
                    'default'     => '',
                    'description' => $descriptions[$key],
                )
            );
        }
    }

    /**
     * Render Banner Slide section description.
     *
     * @since 1.7.0
     */
    public function render_home_banner_slide_section_callback() {
        echo '<p class="description">' . esc_html__('Configure o slide/banner principal da Home.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render O que é section description.
     *
     * @since 1.7.0
     */
    public function render_home_o_que_e_section_callback() {
        echo '<p class="description">' . esc_html__('Seção "O que é" — explique o propósito do movimento.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render O Maior Movimento section description.
     *
     * @since 1.7.0
     */
    public function render_home_maior_movimento_section_callback() {
        echo '<p class="description">' . esc_html__('Seção "O Maior Movimento" — destaque a magnitude do projeto.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render Experiência section description.
     *
     * @since 1.7.0
     */
    public function render_home_experiencia_section_callback() {
        echo '<p class="description">' . esc_html__('Seção "Experiência" — descreva a experiência oferecida.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render CTA section description.
     *
     * @since 1.7.0
     */
    public function render_home_cta_section_callback() {
        echo '<p class="description">' . esc_html__('Seção de Chamada para Ação (CTA).', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render Como Participar section description.
     *
     * @since 1.7.0
     */
    public function render_home_como_participar_section_callback() {
        echo '<p class="description">' . esc_html__('Seção "Como Participar" — instruções de engajamento.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render Compartilhe section description.
     *
     * @since 1.7.0
     */
    public function render_home_compartilhe_section_callback() {
        echo '<p class="description">' . esc_html__('Seção "Compartilhe" — convite para divulgar.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render Atividades section description.
     *
     * @since 1.7.0
     */
    public function render_atividades_section_callback() {
        echo '<p class="description">' . esc_html__('Configure as informações exibidas na página de Atividades.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render Experiências section description.
     *
     * @since 1.7.0
     */
    public function render_experiencias_section_callback() {
        echo '<p class="description">' . esc_html__('Configure as informações exibidas na página de Experiências.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render O Movimento section description.
     *
     * @since 1.7.0
     */
    public function render_movimento_section_callback() {
        echo '<p class="description">' . esc_html__('Configure as informações exibidas na página O Movimento.', 'um-dia-no-parque') . '</p>';
    }

    /**
     * Render a text field for pages settings.
     *
     * @since 1.7.0
     * @param array $args Field arguments.
     */
    public function render_pages_text_field($args) {
        $this->render_text_field($args, $this->pages_option_name, 'get_default_pages_settings');
    }

    /**
     * Render a textarea field for pages settings.
     *
     * @since 1.7.0
     * @param array $args Field arguments.
     */
    public function render_pages_textarea_field($args) {
        $options = get_option($this->pages_option_name, $this->get_default_pages_settings());
        $name    = $args['name'];
        $value   = isset($options[$name]) ? $options[$name] : $args['default'];
        ?>
        <textarea name="<?php echo esc_attr($this->pages_option_name . '[' . $name . ']'); ?>"
                  rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a WYSIWYG editor field for pages settings.
     *
     * @since 1.7.0
     * @param array $args Field arguments.
     */
    public function render_pages_editor_field($args) {
        $options = get_option($this->pages_option_name, $this->get_default_pages_settings());
        $name    = $args['name'];
        $value   = isset($options[$name]) ? $options[$name] : $args['default'];

        wp_editor(
            wp_kses_post($value),
            'umdnp_' . $name,
            array(
                'textarea_name' => $this->pages_option_name . '[' . $name . ']',
                'textarea_rows' => 6,
                'media_buttons' => true,
                'teeny'         => false,
            )
        );

        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Render an image upload field for pages settings.
     *
     * @since 1.7.0
     * @param array $args Field arguments.
     */
    public function render_pages_image_field($args) {
        $options = get_option($this->pages_option_name, $this->get_default_pages_settings());
        $name    = $args['name'];
        $image_id = isset($options[$name]) ? absint($options[$name]) : 0;
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        ?>
        <div class="umdnp-image-field" data-field="<?php echo esc_attr($name); ?>">
            <div class="umdnp-image-preview" style="margin-bottom:8px;">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width:300px; display:block; margin-bottom:8px;">
                <?php endif; ?>
            </div>
            <input type="hidden"
                   name="<?php echo esc_attr($this->pages_option_name . '[' . $name . ']'); ?>"
                   value="<?php echo esc_attr($image_id); ?>"
                   class="umdnp-image-id">
            <button type="button" class="button umdnp-upload-image-btn">
                <?php echo $image_id ? esc_html__('Trocar Imagem', 'um-dia-no-parque') : esc_html__('Selecionar Imagem', 'um-dia-no-parque'); ?>
            </button>
            <?php if ($image_id) : ?>
                <button type="button" class="button umdnp-remove-image-btn" style="margin-left:4px;">
                    <?php esc_html_e('Remover', 'um-dia-no-parque'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a gallery (multiple images) field for pages settings.
     *
     * @since  1.7.0
     * @param  array $args Field arguments.
     */
    public function render_pages_gallery_field($args) {
        $options = get_option($this->pages_option_name, $this->get_default_pages_settings());
        $name    = $args['name'];
        $ids     = isset($options[$name]) ? $options[$name] : '';
        $images  = array();
        if (!empty($ids)) {
            $id_arr = explode(',', $ids);
            foreach ($id_arr as $id) {
                $id = absint(trim($id));
                if ($id) {
                    $url = wp_get_attachment_image_url($id, 'thumbnail');
                    if ($url) {
                        $images[] = array('id' => $id, 'url' => $url);
                    }
                }
            }
        }
        ?>
        <div class="umdnp-gallery-field" data-field="<?php echo esc_attr($name); ?>">
            <div class="umdnp-gallery-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                <?php foreach ($images as $img) : ?>
                    <div class="umdnp-gallery-item" data-id="<?php echo esc_attr($img['id']); ?>" style="position:relative;width:80px;height:80px;border:1px solid #ddd;border-radius:4px;overflow:hidden;">
                        <img src="<?php echo esc_url($img['url']); ?>" style="width:100%;height:100%;object-fit:cover;">
                        <button type="button" class="umdnp-gallery-remove" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:12px;line-height:18px;text-align:center;cursor:pointer;padding:0;">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden"
                   name="<?php echo esc_attr($this->pages_option_name . '[' . $name . ']'); ?>"
                   value="<?php echo esc_attr($ids); ?>"
                   class="umdnp-gallery-ids">
            <button type="button" class="button umdnp-upload-gallery-btn">
                <span class="dashicons dashicons-format-gallery" style="vertical-align:middle;"></span>
                <?php esc_html_e('Gerenciar Galeria', 'um-dia-no-parque'); ?>
            </button>
            <?php if (!empty($ids)) : ?>
                <button type="button" class="button umdnp-gallery-clear-btn" style="margin-left:4px;">
                    <?php esc_html_e('Limpar Galeria', 'um-dia-no-parque'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Get default pages settings.
     *
     * @since  1.7.0
     * @return array
     */
    public function get_default_pages_settings() {
        // Home tab fields.
        $home_fields = array(
            'home_banner_slide_title', 'home_banner_slide_subtitle',
            'home_banner_slide_btn1_text', 'home_banner_slide_btn1_url',
            'home_banner_slide_btn2_text', 'home_banner_slide_btn2_url',
            'home_banner_slide_gallery',
            'home_o_que_e_img1', 'home_o_que_e_img2', 'home_o_que_e_img3',
            'home_o_que_e_title', 'home_o_que_e_subtitle', 'home_o_que_e_text',
            'home_o_que_e_btn_text', 'home_o_que_e_btn_url',
            'home_maior_movimento_img1', 'home_maior_movimento_img2', 'home_maior_movimento_img3',
            'home_maior_movimento_title', 'home_maior_movimento_subtitle', 'home_maior_movimento_text',
            'home_maior_movimento_btn_text', 'home_maior_movimento_btn_url',
            'home_experiencia_title', 'home_experiencia_description',
            'home_cta_title', 'home_cta_description', 'home_cta_btn_text', 'home_cta_btn_url', 'home_cta_image',
            'home_como_participar_title', 'home_como_participar_subtitle', 'home_como_participar_descricao',
            'home_como_participar_card1_icone', 'home_como_participar_card1_titulo', 'home_como_participar_card1_subtitulo',
            'home_como_participar_card2_icone', 'home_como_participar_card2_titulo', 'home_como_participar_card2_subtitulo',
            'home_como_participar_card3_icone', 'home_como_participar_card3_titulo', 'home_como_participar_card3_subtitulo',
            'home_como_participar_card4_icone', 'home_como_participar_card4_titulo', 'home_como_participar_card4_subtitulo',
            'home_compartilhe_title', 'home_compartilhe_subtitle', 'home_compartilhe_descricao', 'home_compartilhe_videos',
        );

        // Other tabs (Atividades, Experiências, O Movimento).
        $other_tabs = array('atividades', 'experiencias', 'movimento');
        $tab_fields = array('title', 'subtitle', 'description', 'hero_image', 'cta_text', 'cta_url');

        $defaults = array();
        foreach ($home_fields as $f) {
            $defaults[$f] = '';
        }
        foreach ($other_tabs as $pref) {
            foreach ($tab_fields as $fld) {
                $defaults["{$pref}_{$fld}"] = '';
            }
        }

        return $defaults;
    }

    /**
     * Sanitize pages settings before saving.
     *
     * @since  1.7.0
     * @param  array $input Raw input.
     * @return array Sanitized input.
     */
    public function sanitize_pages_settings($input) {
        $defaults = $this->get_default_pages_settings();
        $output   = array();

        // Home tab fields.
        $home_sanitizers = array(
            'home_banner_slide_title'    => 'sanitize_text_field',
            'home_banner_slide_subtitle' => 'sanitize_textarea_field',
            'home_banner_slide_btn1_text' => 'sanitize_text_field',
            'home_banner_slide_btn1_url'  => 'esc_url_raw',
            'home_banner_slide_btn2_text' => 'sanitize_text_field',
            'home_banner_slide_btn2_url'  => 'esc_url_raw',
            'home_banner_slide_gallery'   => 'sanitize_text_field',
            'home_o_que_e_img1'         => 'absint',
            'home_o_que_e_img2'         => 'absint',
            'home_o_que_e_img3'         => 'absint',
            'home_o_que_e_title'        => 'sanitize_text_field',
            'home_o_que_e_subtitle'     => 'sanitize_textarea_field',
            'home_o_que_e_text'         => 'wp_kses_post',
            'home_o_que_e_btn_text'     => 'sanitize_text_field',
            'home_o_que_e_btn_url'      => 'esc_url_raw',
            'home_maior_movimento_img1'       => 'absint',
            'home_maior_movimento_img2'       => 'absint',
            'home_maior_movimento_img3'       => 'absint',
            'home_maior_movimento_title'      => 'sanitize_text_field',
            'home_maior_movimento_subtitle'   => 'sanitize_textarea_field',
            'home_maior_movimento_text'       => 'wp_kses_post',
            'home_maior_movimento_btn_text'   => 'sanitize_text_field',
            'home_maior_movimento_btn_url'    => 'esc_url_raw',
            'home_experiencia_title'       => 'sanitize_text_field',
            'home_experiencia_description' => 'wp_kses_post',
            'home_cta_title'       => 'sanitize_text_field',
            'home_cta_description' => 'wp_kses_post',
            'home_cta_btn_text'    => 'sanitize_text_field',
            'home_cta_btn_url'     => 'esc_url_raw',
            'home_cta_image'       => 'absint',
            'home_como_participar_title'       => 'sanitize_text_field',
            'home_como_participar_subtitle'    => 'sanitize_textarea_field',
            'home_como_participar_descricao'   => 'wp_kses_post',
            'home_como_participar_card1_icone' => 'absint',
            'home_como_participar_card1_titulo' => 'sanitize_text_field',
            'home_como_participar_card1_subtitulo' => 'sanitize_textarea_field',
            'home_como_participar_card2_icone' => 'absint',
            'home_como_participar_card2_titulo' => 'sanitize_text_field',
            'home_como_participar_card2_subtitulo' => 'sanitize_textarea_field',
            'home_como_participar_card3_icone' => 'absint',
            'home_como_participar_card3_titulo' => 'sanitize_text_field',
            'home_como_participar_card3_subtitulo' => 'sanitize_textarea_field',
            'home_como_participar_card4_icone' => 'absint',
            'home_como_participar_card4_titulo' => 'sanitize_text_field',
            'home_como_participar_card4_subtitulo' => 'sanitize_textarea_field',
            'home_compartilhe_title'    => 'sanitize_text_field',
            'home_compartilhe_subtitle'  => 'sanitize_textarea_field',
            'home_compartilhe_descricao' => 'wp_kses_post',
            'home_compartilhe_videos'    => 'sanitize_textarea_field',
        );
        foreach ($home_sanitizers as $key => $sanitizer) {
            $raw = isset($input[$key]) ? $input[$key] : $defaults[$key];
            $output[$key] = $this->apply_sanitizer($sanitizer, $raw);
        }

        // Other tabs: Atividades, Experiências, O Movimento.
        $prefixes = array('atividades', 'experiencias', 'movimento');
        $tab_sanitizers = array(
            'title'       => 'sanitize_text_field',
            'subtitle'    => 'sanitize_textarea_field',
            'description' => 'wp_kses_post',
            'hero_image'  => 'absint',
            'cta_text'    => 'sanitize_text_field',
            'cta_url'     => 'esc_url_raw',
        );
        foreach ($prefixes as $pref) {
            foreach ($tab_sanitizers as $fld => $sanitizer) {
                $key = "{$pref}_{$fld}";
                $raw = isset($input[$key]) ? $input[$key] : $defaults[$key];
                $output[$key] = $this->apply_sanitizer($sanitizer, $raw);
            }
        }

        add_settings_error(
            'umdnp_pages_messages',
            'umdnp_pages_saved',
            esc_html__('Configurações da página salvas.', 'um-dia-no-parque'),
            'updated'
        );

        return $output;
    }

    /**
     * Apply a named sanitizer to a value.
     *
     * @since  1.7.0
     * @param  string $sanitizer Sanitizer name.
     * @param  mixed  $raw       Raw value.
     * @return mixed Sanitized value.
     */
    private function apply_sanitizer($sanitizer, $raw) {
        if ('wp_kses_post' === $sanitizer) {
            return wp_kses_post($raw);
        } elseif ('esc_url_raw' === $sanitizer) {
            return esc_url_raw($raw);
        } elseif ('absint' === $sanitizer) {
            $id = absint($raw);
            // Validate that the attachment still exists.
            if ($id > 0 && 'attachment' !== get_post_type($id)) {
                return 0;
            }
            return $id;
        } else {
            return sanitize_text_field($raw);
        }
    }

    // ================================================================
    // AJAX: Clear Cache
    // ================================================================

    /**
     * Handle AJAX cache clearing.
     *
     * @since 1.0.0
     */
    public function ajax_clear_cache() {
        // Verify nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'umdnp_clear_cache_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Erro de segurança. Recarregue a página.', 'um-dia-no-parque')));
        }

        // Check capability.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Permissão negada.', 'um-dia-no-parque')));
        }

        $cache_type = isset($_POST['cache_type']) ? sanitize_key($_POST['cache_type']) : '';
        $messages   = array();

        switch ($cache_type) {
            case 'transients':
                $this->clear_plugin_transients();
                $messages[] = esc_html__('Transientes limpos com sucesso.', 'um-dia-no-parque');
                break;

            case 'logs':
                Um_Dia_No_Parque_Logger::clear_logs();
                $messages[] = esc_html__('Logs limpos com sucesso.', 'um-dia-no-parque');
                break;

            case 'elementor_css':
                if ($this->clear_elementor_css_cache()) {
                    $messages[] = esc_html__('Cache CSS do Elementor limpo com sucesso.', 'um-dia-no-parque');
                } else {
                    $messages[] = esc_html__('Elementor não está ativo.', 'um-dia-no-parque');
                }
                break;

            case 'rewrite_rules':
                $this->clear_rewrite_rules();
                $messages[] = esc_html__('Regras de rewrite recarregadas.', 'um-dia-no-parque');
                break;

            case 'seed_cidades':
                delete_transient('umdnp_cidades_seed');
                delete_option('umdnp_cidades_seeded');
                Um_Dia_No_Parque_Seed::get_instance()->seed_all();
                $messages[] = esc_html__('Estados e cidades atualizados com sucesso.', 'um-dia-no-parque');
                break;

            case 'all':
                $this->clear_plugin_transients();
                $this->clear_elementor_css_cache();
                $this->clear_rewrite_rules();
                Um_Dia_No_Parque_Logger::clear_logs();
                $messages[] = esc_html__('Todos os caches e logs foram limpos.', 'um-dia-no-parque');
                break;

            default:
                wp_send_json_error(array('message' => esc_html__('Tipo de cache inválido.', 'um-dia-no-parque')));
        }

        wp_send_json_success(array('message' => implode('<br>', $messages)));
    }

    /**
     * Clear plugin transients.
     *
     * @since 1.0.0
     */
    private function clear_plugin_transients() {
        global $wpdb;

        // Delete transients with our prefix.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_umdnp_') . '%',
                $wpdb->esc_like('_transient_timeout_umdnp_') . '%'
            )
        );

        // Also handle Elementor transients if they exist.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_um_dia_no_parque_') . '%',
                $wpdb->esc_like('_transient_timeout_um_dia_no_parque_') . '%'
            )
        );

        Um_Dia_No_Parque_Logger::info('Cache: transientes limpos');

        /**
         * Fires after plugin transients have been cleared.
         *
         * @since 1.0.0
         */
        do_action('umdnp_after_clear_transients');
    }

    /**
     * Clear Elementor CSS cache.
     *
     * @since  1.0.0
     * @return bool Whether Elementor was active and cache was cleared.
     */
    private function clear_elementor_css_cache() {
        if (!did_action('elementor/loaded') && !defined('ELEMENTOR_VERSION')) {
            Um_Dia_No_Parque_Logger::warning('Cache: Elementor não ativo para limpar CSS');
            return false;
        }

        if (class_exists('\\Elementor\\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        // Also delete Elementor's post meta CSS.
        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => '_elementor_css')
        );

        Um_Dia_No_Parque_Logger::info('Cache: CSS do Elementor limpo');
        return true;
    }

    /**
     * Flush rewrite rules.
     *
     * @since 1.0.0
     */
    private function clear_rewrite_rules() {
        flush_rewrite_rules(true);
    }

    // ================================================================
    // System Info Data
    // ================================================================

    /**
     * Get WordPress environment info.
     *
     * @since  1.0.0
     * @return array
     */
    // ================================================================
    // Admin Assets
    // ================================================================

    /**
     * Enqueue admin styles and scripts for our pages only.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages.
        if (false === strpos($hook, $this->plugin_slug)) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            $this->plugin_slug . '-admin-settings',
            UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/css/im-dia-no-parque-admin.css',
            array(),
            UM_DIA_NO_PARQUE_VERSION
        );

        wp_enqueue_style('dashicons');

        wp_enqueue_script(
            $this->plugin_slug . '-admin-settings',
            UM_DIA_NO_PARQUE_PLUGIN_URL . 'assets/js/im-dia-no-parque-admin.js',
            array('jquery'),
            UM_DIA_NO_PARQUE_VERSION,
            true
        );

        // Import page — localize extra vars
        if (isset($_GET['tab']) && 'import' === $_GET['tab']) {
            wp_localize_script(
                $this->plugin_slug . '-admin-settings',
                'umdnp_import',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('umdnp_import_nonce'),
                    'i18n'     => array(
                        'uploading'        => __('Enviando arquivo...', 'um-dia-no-parque'),
                        'processing'       => __('Importando dados...', 'um-dia-no-parque'),
                        'done'             => __('Importação concluída!', 'um-dia-no-parque'),
                        'error'            => __('Erro', 'um-dia-no-parque'),
                        'confirm_cancel'   => __('Tem certeza que deseja cancelar?', 'um-dia-no-parque'),
                        'processing_of'    => __('Processando %1$d de %2$d...', 'um-dia-no-parque'),
                    ),
                )
            );
        }
    }

    // ================================================================
    // Plugin Action Links
    // ================================================================

    /**
     * Add settings link on the plugins screen.
     *
     * @since  1.0.0
     * @param  array $links Existing plugin action links.
     * @return array Modified links.
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . $this->plugin_slug)),
            esc_html__('Configurações', 'um-dia-no-parque')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Render the import tab.
     *
     * Shows the file upload form, progress bar, and results summary.
     *
     * @since 1.9.0
     */
    private function render_import_tab() {
        $max_upload = size_format(wp_max_upload_size());
        ?>
        <div class="umdnp-import-tab">
            <h2><?php esc_html_e('Importar Dados', 'um-dia-no-parque'); ?></h2>
            <p class="description">
                <?php esc_html_e('Faça upload de um arquivo CSV ou XLSX para importar Unidades de Conservação (UCs) e Atividades.', 'um-dia-no-parque'); ?>
                <br>
                <?php esc_html_e('Tamanho máximo do arquivo:', 'um-dia-no-parque'); ?> <strong><?php echo esc_html($max_upload); ?></strong>
            </p>

            <div class="umdnp-import-upload-section" id="umdnp-import-upload-section">
                <form id="umdnp-import-form" method="post" enctype="multipart/form-data">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="umdnp-import-file"><?php esc_html_e('Arquivo', 'um-dia-no-parque'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="umdnp-import-file" name="import_file"
                                           accept=".csv,.xlsx,.xls" style="width:auto;">
                                    <p class="description">
                                        <?php esc_html_e('Formatos aceitos: CSV, XLSX.', 'um-dia-no-parque'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" id="umdnp-import-upload-btn" class="button button-primary">
                            <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-top:-2px;"></span>
                            <?php esc_html_e('Enviar e Importar', 'um-dia-no-parque'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div class="umdnp-import-progress-section" id="umdnp-import-progress-section" style="display:none;">
                <h3><?php esc_html_e('Progresso da Importação', 'um-dia-no-parque'); ?></h3>

                <div class="umdnp-import-progress-bar" style="
                    background:#f0f0f1;
                    border-radius:4px;
                    height:24px;
                    margin:12px 0;
                    overflow:hidden;
                    border:1px solid #c3c4c7;
                ">
                    <div id="umdnp-import-progress-fill" style="
                        background:#2271b1;
                        width:0%;
                        height:100%;
                        transition:width 0.3s ease;
                        border-radius:3px;
                    "></div>
                </div>
                <p>
                    <strong><?php esc_html_e('Status:', 'um-dia-no-parque'); ?></strong>
                    <span id="umdnp-import-status"><?php esc_html_e('Aguardando...', 'um-dia-no-parque'); ?></span>
                </p>

                <div id="umdnp-import-message" class="notice" style="display:none;"></div>

                <p id="umdnp-import-actions">
                    <button type="button" id="umdnp-import-cancel-btn" class="button" style="display:none;">
                        <?php esc_html_e('Cancelar', 'um-dia-no-parque'); ?>
                    </button>
                    <button type="button" id="umdnp-import-start-btn" class="button" style="display:none;">
                        <?php esc_html_e('Iniciar Importação', 'um-dia-no-parque'); ?>
                    </button>
                </p>
            </div>

            <div id="umdnp-import-results" style="display:none;margin-top:16px;">
                <h3><?php esc_html_e('Resultado', 'um-dia-no-parque'); ?></h3>
                <table class="widefat striped" style="max-width:400px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Item', 'um-dia-no-parque'); ?></th>
                            <th><?php esc_html_e('Quantidade', 'um-dia-no-parque'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('UCs importadas', 'um-dia-no-parque'); ?></td>
                            <td id="umdnp-import-result-ucs">0</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Atividades importadas', 'um-dia-no-parque'); ?></td>
                            <td id="umdnp-import-result-atividades">0</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Erros', 'um-dia-no-parque'); ?></td>
                            <td id="umdnp-import-result-errors">0</td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="button" id="umdnp-import-reset-btn" class="button">
                        <span class="dashicons dashicons-arrow-left-alt" style="vertical-align:middle;margin-top:-2px;"></span>
                        <?php esc_html_e('Nova Importação', 'um-dia-no-parque'); ?>
                    </button>
                </p>
            </div>

            <style>
                .umdnp-import-tab .dashicons {
                    vertical-align: middle;
                    margin-top: -2px;
                }
                .umdnp-import-tab .form-table th {
                    width: 120px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Render the reset (redefinir) tab.
     *
     * @since 1.8.0
     */
    private function render_reset_tab() {
        $nonce = wp_create_nonce('umdnp_reset_all_nonce');

        $items = array(
            'ucs' => array(
                'label' => __('Unidades de Conservação (UCs)', 'um-dia-no-parque'),
                'desc'  => __('Remove todos os parques cadastrados.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-palmtree',
            ),
            'atividades' => array(
                'label' => __('Atividades', 'um-dia-no-parque'),
                'desc'  => __('Remove todas as atividades.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-games',
            ),
            'depoimentos' => array(
                'label' => __('Depoimentos', 'um-dia-no-parque'),
                'desc'  => __('Remove todos os depoimentos.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-testimonial',
            ),
            'parceiros' => array(
                'label' => __('Parceiros', 'um-dia-no-parque'),
                'desc'  => __('Remove todos os parceiros.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-groups',
            ),
            'referencia' => array(
                'label' => __('Dados de Referência', 'um-dia-no-parque'),
                'desc'  => __('Remove UFs e "O que levar".', 'um-dia-no-parque'),
                'icon'  => 'dashicons-admin-site',
            ),
            'configuracoes' => array(
                'label' => __('Configurações', 'um-dia-no-parque'),
                'desc'  => __('Remove todas as configurações do plugin e das páginas.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-admin-generic',
            ),
            'transientes_logs' => array(
                'label' => __('Transientes e Logs', 'um-dia-no-parque'),
                'desc'  => __('Remove caches temporários e arquivos de log.', 'um-dia-no-parque'),
                'icon'  => 'dashicons-trash',
            ),
        );
        ?>
        <div class="umdnp-reset-tab">
            <h2><?php esc_html_e('Redefinir Plugin', 'um-dia-no-parque'); ?></h2>

            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Atenção!', 'um-dia-no-parque'); ?></strong>
                    <?php esc_html_e('Selecione os dados que deseja remover. As operações não podem ser desfeitas.', 'um-dia-no-parque'); ?>
                </p>
            </div>

            <div id="umdnp-reset-message" class="notice" style="display:none;"></div>

            <div class="umdnp-reset-items">
                <div class="umdnp-reset-select-all">
                    <label>
                        <input type="checkbox" id="umdnp-reset-toggle-all" checked>
                        <strong><?php esc_html_e('Selecionar / Desmarcar Todos', 'um-dia-no-parque'); ?></strong>
                    </label>
                </div>

                <?php foreach ($items as $key => $item) : ?>
                    <div class="umdnp-reset-item">
                        <label>
                            <input type="checkbox" class="umdnp-reset-check" name="reset_types[]" value="<?php echo esc_attr($key); ?>" checked>
                            <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                            <span class="umdnp-reset-item-label"><?php echo esc_html($item['label']); ?></span>
                        </label>
                        <p class="umdnp-reset-item-desc"><?php echo esc_html($item['desc']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Progress bar (hidden initially) -->
            <div id="umdnp-reset-progress-wrap" style="display:none; margin-bottom:24px;">
                <div class="umdnp-reset-progress-bar">
                    <div id="umdnp-reset-progress-fill" class="umdnp-reset-progress-fill"></div>
                </div>
                <p id="umdnp-reset-progress-label" class="umdnp-reset-progress-label">
                    <?php esc_html_e('Removendo dados...', 'um-dia-no-parque'); ?>
                </p>
            </div>

            <!-- Certification table (hidden initially) -->
            <div id="umdnp-reset-certification" style="display:none; margin-bottom:24px;">
                <div class="umdnp-reset-cert-head">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php esc_html_e('Certificado de Remoção', 'um-dia-no-parque'); ?></strong>
                </div>
                <table class="widefat striped umdnp-reset-cert-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Item', 'um-dia-no-parque'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Antes', 'um-dia-no-parque'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Removidos', 'um-dia-no-parque'); ?></th>
                            <th style="width:90px;"><?php esc_html_e('Status', 'um-dia-no-parque'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="umdnp-reset-cert-body"></tbody>
                </table>
                <p id="umdnp-reset-cert-footer" class="umdnp-reset-cert-footer"></p>
            </div>

            <div class="umdnp-reset-actions">
                <button type="button" class="button button-primary umdnp-reset-all-btn" data-nonce="<?php echo esc_attr($nonce); ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Remover Dados Selecionados', 'um-dia-no-parque'); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle all checkboxes.
            $('#umdnp-reset-toggle-all').on('change', function() {
                $('.umdnp-reset-check').prop('checked', $(this).is(':checked'));
            });

            // Uncheck "select all" when any individual is unchecked.
            $('.umdnp-reset-check').on('change', function() {
                var allChecked = $('.umdnp-reset-check').length === $('.umdnp-reset-check:checked').length;
                $('#umdnp-reset-toggle-all').prop('checked', allChecked);
            });

            $('.umdnp-reset-all-btn').on('click', function() {
                var $btn = $(this);
                var checked = [];

                $('.umdnp-reset-check:checked').each(function() {
                    checked.push($(this).val());
                });

                if (checked.length === 0) {
                    alert('<?php echo esc_js(__('Selecione ao menos um item para remover.', 'um-dia-no-parque')); ?>');
                    return;
                }

                if (!confirm('<?php echo esc_js(__('TEM CERTEZA? Os itens selecionados serão removidos PERMANENTEMENTE. Esta operação NÃO pode ser desfeita.', 'um-dia-no-parque')); ?>')) {
                    return;
                }

                var confirmation = prompt('<?php echo esc_js(__('Digite REMOVER para confirmar:', 'um-dia-no-parque')); ?>');
                if (confirmation !== 'REMOVER') {
                    alert('<?php echo esc_js(__('Confirmação incorreta. Operação cancelada.', 'um-dia-no-parque')); ?>');
                    return;
                }

                // Show progress bar, hide previous results.
                $('#umdnp-reset-progress-wrap').show();
                $('#umdnp-reset-certification').hide();
                $('#umdnp-reset-message').hide();
                var $fill = $('#umdnp-reset-progress-fill');
                var $label = $('#umdnp-reset-progress-label');
                $fill.css('width', '10%');
                $label.text('<?php echo esc_js(__('Removendo dados...', 'um-dia-no-parque')); ?>');
                setTimeout(function() { $fill.css('width', '30%'); }, 200);
                setTimeout(function() { $fill.css('width', '60%'); }, 500);

                var totalSteps = checked.length;
                var stepLabels = {
                    ucs: '<?php echo esc_js(__('UCs', 'um-dia-no-parque')); ?>',
                    atividades: '<?php echo esc_js(__('Atividades', 'um-dia-no-parque')); ?>',
                    depoimentos: '<?php echo esc_js(__('Depoimentos', 'um-dia-no-parque')); ?>',
                    parceiros: '<?php echo esc_js(__('Parceiros', 'um-dia-no-parque')); ?>',
                    referencia: '<?php echo esc_js(__('Referência', 'um-dia-no-parque')); ?>',
                    configuracoes: '<?php echo esc_js(__('Configurações', 'um-dia-no-parque')); ?>',
                    transientes_logs: '<?php echo esc_js(__('Transientes/Logs', 'um-dia-no-parque')); ?>'
                };
                var stepIcons = {
                    ucs: 'dashicons-palmtree',
                    atividades: 'dashicons-games',
                    depoimentos: 'dashicons-testimonial',
                    parceiros: 'dashicons-groups',
                    referencia: 'dashicons-admin-site',
                    configuracoes: 'dashicons-admin-generic',
                    transientes_logs: 'dashicons-trash'
                };

                var originalText = $btn.html();
                $btn.prop('disabled', true).html(
                    '<span class="dashicons dashicons-update spinning"></span> <?php echo esc_js(__('Removendo...', 'um-dia-no-parque')); ?>'
                );

                $.post(ajaxurl, {
                    action: 'umdnp_reset_all',
                    nonce: $btn.data('nonce'),
                    types: checked
                })
                .done(function(response) {
                    $fill.css('width', '100%');
                    $label.text('<?php echo esc_js(__('Verificando resultados...', 'um-dia-no-parque')); ?>');

                    setTimeout(function() {
                        $('#umdnp-reset-progress-wrap').fadeOut(300);
                        renderCertification(response, checked, stepLabels, stepIcons);
                    }, 400);
                })
                .fail(function() {
                    $fill.css('width', '100%');
                    $label.text('<?php echo esc_js(__('Erro na requisição.', 'um-dia-no-parque')); ?>');
                    var $msg = $('#umdnp-reset-message');
                    $msg.show().removeClass('notice-success').addClass('notice-error');
                    $msg.html('<p><?php echo esc_js(__('Erro na requisição. Tente novamente.', 'um-dia-no-parque')); ?></p>');
                    $btn.prop('disabled', false).html(originalText);
                });

                /**
                 * Render the certification table after removal.
                 */
                function renderCertification(response, checked, stepLabels, stepIcons) {
                    var $msg = $('#umdnp-reset-message');
                    var cert = response.data && response.data.certification;
                    var $certBody = $('#umdnp-reset-cert-body');
                    var hasOk = false;

                    $certBody.empty();

                    $.each(checked, function(_, type) {
                        var label = stepLabels[type] || type;
                        var dashicon = stepIcons[type] || 'dashicons-trash';
                        var row;

                        if (cert && cert[type]) {
                            var c = cert[type];
                            var before = c.before || 0;
                            var removed = c.removed || 0;
                            var ok = (c.status === 'ok');
                            if (ok) hasOk = true;
                            var statusIcon = ok
                                ? '<span class="dashicons dashicons-yes" style="color:#00a32a;"></span>'
                                : '<span class="dashicons dashicons-warning" style="color:#d63638;"></span>';
                            var removedLabel = c.message || removed;
                            row = '<tr>' +
                                '<td><span class="dashicons ' + dashicon + '" style="margin-right:6px;"></span>' + label + '</td>' +
                                '<td>' + before + '</td>' +
                                '<td>' + removedLabel + '</td>' +
                                '<td>' + statusIcon + '</td>' +
                            '</tr>';
                        } else if (response.data && response.data.removed && response.data.removed.indexOf(type) !== -1) {
                            hasOk = true;
                            row = '<tr>' +
                                '<td><span class="dashicons ' + dashicon + '" style="margin-right:6px;"></span>' + label + '</td>' +
                                '<td>—</td>' +
                                '<td><?php echo esc_js(__('Removido', 'um-dia-no-parque')); ?></td>' +
                                '<td><span class="dashicons dashicons-yes" style="color:#00a32a;"></span></td>' +
                            '</tr>';
                        } else {
                            row = '<tr>' +
                                '<td><span class="dashicons ' + dashicon + '" style="margin-right:6px;"></span>' + label + '</td>' +
                                '<td>—</td>' +
                                '<td>—</td>' +
                                '<td><span class="dashicons dashicons-minus" style="color:#a7aaad;"></span></td>' +
                            '</tr>';
                        }

                        $certBody.append(row);
                    });

                    if (response.success) {
                        $('#umdnp-reset-cert-footer').text('<?php echo esc_js(__('✓ Todos os dados selecionados foram removidos com sucesso.', 'um-dia-no-parque')); ?>');
                    } else {
                        $('#umdnp-reset-cert-footer').text(
                            response.data && response.data.message
                                ? response.data.message
                                : '<?php echo esc_js(__('Alguns itens podem não ter sido removidos completamente.', 'um-dia-no-parque')); ?>'
                        );
                    }

                    $('#umdnp-reset-certification').fadeIn(300);
                    $msg.hide();
                    $btn.html('<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Concluído', 'um-dia-no-parque')); ?>');

                    // Uncheck removed items.
                    $.each(response.data && response.data.removed || [], function(_, type) {
                        $('.umdnp-reset-check[value="' + type + '"]').prop('checked', false);
                    });
                    var allChecked = $('.umdnp-reset-check').length === $('.umdnp-reset-check:checked').length;
                    $('#umdnp-reset-toggle-all').prop('checked', allChecked);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX reset all — removes selected plugin data.
     *
     * @since 1.8.0
     */
    public function ajax_reset_all() {
        // Verify nonce.
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'umdnp_reset_all_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Erro de segurança. Recarregue a página.', 'um-dia-no-parque')));
        }

        // Check capability.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Permissão negada.', 'um-dia-no-parque')));
        }

        $types  = isset($_POST['types']) ? array_map('sanitize_key', (array) $_POST['types']) : array();
        $errors = array();
        $removed = array();
        $certification = array();

        if (empty($types)) {
            wp_send_json_error(array('message' => esc_html__('Nenhum tipo selecionado para remoção.', 'um-dia-no-parque')));
        }

        // Helper: map cert key → CPT slugs for pre-count.
        $cpt_map = array(
            'ucs'         => 'uc',
            'atividades'  => 'atividade',
            'depoimentos' => 'depoimento',
            'parceiros'   => 'parceiro',
            'referencia'  => array('uf', 'oque_levar'),
        );

        /**
         * Count published (any status) posts for a given post type.
         */
        $count_cpt = function ($pt) {
            $counts = wp_count_posts($pt);
            $total  = 0;
            if (is_object($counts)) {
                foreach (get_object_vars($counts) as $status => $n) {
                    $total += (int) $n;
                }
            }
            return $total;
        };

        /**
         * Count before, delete, return result.
         */
        $delete_with_cert = function ($type_key) use ($cpt_map, &$errors, &$certification, $count_cpt) {
            $slugs = $cpt_map[$type_key];
            $slugs = is_array($slugs) ? $slugs : array($slugs);

            $before = 0;
            foreach ($slugs as $slug) {
                $before += $count_cpt($slug);
            }

            $removed_count = 0;
            foreach ($slugs as $slug) {
                $removed_count += $this->delete_cpt_posts($slug, $errors);
            }

            $status = ($removed_count === $before) ? 'ok' : 'partial';
            $certification[$type_key] = array(
                'before'  => $before,
                'removed' => $removed_count,
                'status'  => $status,
            );

            return $removed_count;
        };

        // 1. UCs
        if (in_array('ucs', $types, true)) {
            $delete_with_cert('ucs');
            $removed[] = 'ucs';
        }

        // 2. Atividades
        if (in_array('atividades', $types, true)) {
            $delete_with_cert('atividades');
            $removed[] = 'atividades';
        }

        // 3. Depoimentos
        if (in_array('depoimentos', $types, true)) {
            $delete_with_cert('depoimentos');
            $removed[] = 'depoimentos';
        }

        // 4. Parceiros
        if (in_array('parceiros', $types, true)) {
            $delete_with_cert('parceiros');
            $removed[] = 'parceiros';
        }

        // 5. Dados de referência (UFs, O que levar)
        if (in_array('referencia', $types, true)) {
            $before = 0;
            $removed_count = 0;
            foreach (array('uf', 'oque_levar') as $slug) {
                $before += $count_cpt($slug);
                $removed_count += $this->delete_cpt_posts($slug, $errors);
            }
            $certification['referencia'] = array(
                'before'  => $before,
                'removed' => $removed_count,
                'status'  => ($removed_count === $before) ? 'ok' : 'partial',
            );
            $removed[] = 'referencia';
        }

        // 6. Configurações
        if (in_array('configuracoes', $types, true)) {
            $options = array(
                'um_dia_no_parque_version',
                'um_dia_no_parque_settings',
                'um_dia_no_parque_pages',
            );
            $found = 0;
            foreach ($options as $option) {
                if (false !== get_option($option)) {
                    $found++;
                }
                delete_option($option);
                delete_site_option($option);
            }
            $certification['configuracoes'] = array(
                'before'  => $found,
                'removed' => $found,
                'status'  => 'ok',
                'message' => sprintf(
                    /* translators: %d: number of options cleared */
                    __('%d opções', 'um-dia-no-parque'),
                    $found
                ),
            );
            $removed[] = 'configuracoes';
        }

        // 7. Transientes e Logs
        if (in_array('transientes_logs', $types, true)) {
            $this->clear_plugin_transients();
            Um_Dia_No_Parque_Logger::clear_logs();
            wp_clear_scheduled_hook('umdnp_log_cleanup');
            $certification['transientes_logs'] = array(
                'before'  => '—',
                'removed' => '—',
                'status'  => 'ok',
                'message' => __('Cache e logs limpos', 'um-dia-no-parque'),
            );
            $removed[] = 'transientes_logs';
        }

        // Flush rewrite rules if any content was removed.
        if (!empty(array_intersect($types, array('ucs', 'atividades', 'depoimentos', 'parceiros', 'referencia')))) {
            flush_rewrite_rules(true);
        }

        // Log the reset action.
        Um_Dia_No_Parque_Logger::info(
            sprintf(
                'Plugin: dados removidos via painel admin — %s',
                implode(', ', $removed)
            )
        );

        if (!empty($errors)) {
            $message = esc_html__('Remoção concluída com alguns erros:', 'um-dia-no-parque') . '<br>' . implode('<br>', array_map('esc_html', $errors));
            wp_send_json_error(array('message' => $message, 'removed' => $removed, 'certification' => $certification));
        }

        wp_send_json_success(array(
            'message'       => esc_html__('Dados removidos com sucesso!', 'um-dia-no-parque'),
            'removed'       => $removed,
            'certification' => $certification,
        ));
    }

    /**
     * Delete all posts of a given post type.
     *
     * @since  1.8.0
     * @param  string $post_type Post type slug.
     * @param  array  $errors    Reference to errors array.
     * @return int               Number of successfully deleted posts.
     */
    private function delete_cpt_posts($post_type, array &$errors) {
        $posts = get_posts(array(
            'post_type'      => $post_type,
            'numberposts'    => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        $deleted = 0;
        foreach ($posts as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted++;
            } else {
                $errors[] = sprintf(
                    /* translators: 1: post type, 2: post ID */
                    __('Falha ao remover %1$s ID %2$d', 'um-dia-no-parque'),
                    $post_type,
                    $post_id
                );
            }
        }

        return $deleted;
    }
}
