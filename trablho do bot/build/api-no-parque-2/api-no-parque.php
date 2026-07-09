<?php
/**
 * Plugin Name: API No Parque
 * Plugin URI: https://barradois.com
 * Description: API e importador para integrar a base JSON com os cadastros do plugin Um Dia No Parque.
 * Version: 0.2.1
 * Author: Diovanni de Souza
 * Author URI: https://barradois.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: api-no-parque
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Api_No_Parque {
    const VERSION = '0.2.1';
    const REST_NAMESPACE = 'api-no-parque/v1';
    const ACTIVITY_TYPES_VERSION = '2026-07-08-3';
    const ACTIVITY_CLASSIFICATIONS_VERSION = '2026-07-08-2';
    const CARRY_ITEMS_VERSION = '2026-07-08-2';
    const META_ACTIVITY_TYPE_ICON = '_api_np_fa_icon';
    const META_ACTIVITY_UC_ID = '_atividade_uc_id';
    const META_UC_CARRY_ITEM_IDS = '_uc_oque_levar_ids';
    const META_CARRY_ITEM_ICON = '_oque_levar_icone';

    const META_SOURCE_ID = '_api_np_source_id';
    const META_LAT = '_api_np_lat';
    const META_LNG = '_api_np_lng';
    const META_LOCATION_SOURCE = '_api_np_location_source';
    const META_LOCATION_PRECISION = '_api_np_location_precision';
    const META_LOCATION_QUERY = '_api_np_location_query';
    const META_LOCATION_DISPLAY_NAME = '_api_np_location_display_name';
    const META_IMPORTED_AT = '_api_np_imported_at';
    const META_ACTIVITY_SOURCE_KEY = '_api_np_activity_source_key';

    private static $instance = null;

    private $uf_by_state = array(
        'acre' => 'AC',
        'alagoas' => 'AL',
        'amapa' => 'AP',
        'amazonas' => 'AM',
        'bahia' => 'BA',
        'ceara' => 'CE',
        'distrito federal' => 'DF',
        'espirito santo' => 'ES',
        'goias' => 'GO',
        'maranhao' => 'MA',
        'mato grosso' => 'MT',
        'mato grosso do sul' => 'MS',
        'minas gerais' => 'MG',
        'para' => 'PA',
        'paraiba' => 'PB',
        'parana' => 'PR',
        'pernambuco' => 'PE',
        'piaui' => 'PI',
        'rio de janeiro' => 'RJ',
        'rio grande do norte' => 'RN',
        'rio grande do sul' => 'RS',
        'rondonia' => 'RO',
        'roraima' => 'RR',
        'santa catarina' => 'SC',
        'sao paulo' => 'SP',
        'sergipe' => 'SE',
        'tocantins' => 'TO',
    );

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('init', array($this, 'customize_uc_admin_support'), 100);
        add_action('admin_init', array($this, 'maybe_sync_activity_types'));
        add_action('admin_init', array($this, 'maybe_normalize_activity_classifications'));
        add_action('admin_init', array($this, 'maybe_sync_carry_items'));
        add_action('add_meta_boxes', array($this, 'replace_uc_meta_box'), 100, 2);
        add_action('add_meta_boxes', array($this, 'replace_activity_meta_box'), 100, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_uc_admin_assets'));
        add_action('save_post_uc', array($this, 'sync_uc_activity_relations_from_uc_form'), 20, 2);
        add_action('save_post_uc', array($this, 'save_uc_carry_items'), 21, 2);
        add_action('save_post_atividade', array($this, 'save_activity_uc_relation'), 20, 2);
        add_filter('manage_edit-tipo_atividade_columns', array($this, 'activity_type_columns'));
        add_filter('manage_tipo_atividade_custom_column', array($this, 'activity_type_column_content'), 10, 3);
        add_filter('enter_title_here', array($this, 'filter_uc_title_placeholder'), 10, 2);
        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('admin_post_api_np_import_json', array($this, 'handle_admin_import'));
        add_action('admin_post_api_np_purge_data', array($this, 'handle_admin_purge'));
        add_action('admin_notices', array($this, 'legacy_admin_notice'));
    }

    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_status'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::REST_NAMESPACE, '/map', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_collection'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::REST_NAMESPACE, '/list', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_collection'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::REST_NAMESPACE, '/ucs', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_collection'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_create_uc'),
                'permission_callback' => array($this, 'can_manage'),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/ucs/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_get_uc'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'id' => array('sanitize_callback' => 'absint'),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'rest_update_uc'),
                'permission_callback' => array($this, 'can_manage'),
                'args' => array(
                    'id' => array('sanitize_callback' => 'absint'),
                ),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'rest_delete_uc'),
                'permission_callback' => array($this, 'can_manage'),
                'args' => array(
                    'id' => array('sanitize_callback' => 'absint'),
                ),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/import', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_import'),
            'permission_callback' => array($this, 'can_manage'),
        ));

        register_rest_route(self::REST_NAMESPACE, '/purge', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'rest_purge'),
            'permission_callback' => array($this, 'can_manage'),
        ));
    }

    public function can_manage() {
        return current_user_can('manage_options');
    }

    public function legacy_admin_notice() {
        if (!$this->legacy_available()) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('API No Parque: o plugin legado Um Dia No Parque precisa estar ativo para importar ou ler UCs reais.', 'api-no-parque');
            echo '</p></div>';
        }
    }

    public function register_admin_page() {
        add_management_page(
            __('API No Parque', 'api-no-parque'),
            __('API No Parque', 'api-no-parque'),
            'manage_options',
            'api-no-parque',
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $result = get_transient('api_np_last_import_result');
        delete_transient('api_np_last_import_result');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('API No Parque', 'api-no-parque'); ?></h1>
            <p><?php esc_html_e('Importe a base JSON e exponha os dados no padrao dos componentes de mapa e lista.', 'api-no-parque'); ?></p>

            <h2><?php esc_html_e('Status', 'api-no-parque'); ?></h2>
            <table class="widefat striped" style="max-width:760px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Plugin legado disponivel', 'api-no-parque'); ?></th>
                        <td><?php echo $this->legacy_available() ? esc_html__('Sim', 'api-no-parque') : esc_html__('Nao', 'api-no-parque'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('UCs publicadas', 'api-no-parque'); ?></th>
                        <td><?php echo esc_html((string) $this->count_posts('uc')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Atividades publicadas', 'api-no-parque'); ?></th>
                        <td><?php echo esc_html((string) $this->count_posts('atividade')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Endpoint mapa/lista', 'api-no-parque'); ?></th>
                        <td><code><?php echo esc_html(rest_url(self::REST_NAMESPACE . '/map')); ?></code></td>
                    </tr>
                </tbody>
            </table>

            <?php if (is_array($result)) : ?>
                <div class="notice notice-<?php echo empty($result['ok']) ? 'error' : 'success'; ?> is-dismissible" style="margin-top:16px;">
                    <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                    <?php if (!empty($result['stats'])) : ?>
                        <pre style="white-space:pre-wrap;"><?php echo esc_html(wp_json_encode($result['stats'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Importar JSON', 'api-no-parque'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:16px;">
                <?php wp_nonce_field('api_np_import_json', 'api_np_nonce'); ?>
                <input type="hidden" name="action" value="api_np_import_json">
                <p>
                    <label for="api_np_json"><strong><?php esc_html_e('Arquivo JSON', 'api-no-parque'); ?></strong></label><br>
                    <input type="file" id="api_np_json" name="api_np_json" accept="application/json,.json" required>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="dry_run" value="1">
                        <?php esc_html_e('Simular importacao sem gravar dados', 'api-no-parque'); ?>
                    </label>
                </p>
                <p><?php submit_button(__('Importar JSON', 'api-no-parque'), 'primary', 'submit', false); ?></p>
            </form>

            <h2><?php esc_html_e('Limpeza da base', 'api-no-parque'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:16px;border-left:4px solid #b32d2e;">
                <?php wp_nonce_field('api_np_purge_data', 'api_np_purge_nonce'); ?>
                <input type="hidden" name="action" value="api_np_purge_data">
                <p><?php esc_html_e('Remove UCs, atividades e termos relacionados. Use apenas com backup.', 'api-no-parque'); ?></p>
                <p>
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        <?php esc_html_e('Simular limpeza sem apagar dados', 'api-no-parque'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="delete_terms" value="1">
                        <?php esc_html_e('Apagar termos de bioma, cidade, dificuldade, publico e tipo_atividade', 'api-no-parque'); ?>
                    </label>
                </p>
                <p>
                    <label for="api_np_confirm"><strong><?php esc_html_e('Confirmacao', 'api-no-parque'); ?></strong></label><br>
                    <input type="text" id="api_np_confirm" name="confirm" class="regular-text" placeholder="LIMPAR BASE">
                </p>
                <p><?php submit_button(__('Executar limpeza', 'api-no-parque'), 'delete', 'submit', false); ?></p>
            </form>
        </div>
        <?php
    }

    public function customize_uc_admin_support() {
        if (post_type_exists('uc')) {
            remove_post_type_support('uc', 'editor');
            remove_post_type_support('uc', 'excerpt');
        }

        if (post_type_exists('atividade')) {
            remove_post_type_support('atividade', 'editor');
            remove_post_type_support('atividade', 'excerpt');
        }
    }

    public function filter_uc_title_placeholder($placeholder, $post) {
        if ($post && 'uc' === $post->post_type) {
            return __('Nome da Unidade de Conservacao', 'api-no-parque');
        }

        if ($post && 'atividade' === $post->post_type) {
            return __('Nome da Atividade', 'api-no-parque');
        }

        return $placeholder;
    }

    public function replace_uc_meta_box($post_type, $post) {
        if ('uc' !== $post_type) {
            return;
        }

        remove_meta_box('uc_dados', 'uc', 'normal');

        add_meta_box(
            'api_np_uc_dados',
            __('Cadastro da UC', 'api-no-parque'),
            array($this, 'render_uc_admin_form'),
            'uc',
            'normal',
            'high'
        );
    }

    public function replace_activity_meta_box($post_type, $post) {
        if ('atividade' !== $post_type) {
            return;
        }

        remove_meta_box('atividade_dados', 'atividade', 'normal');
        remove_meta_box('dificuldadediv', 'atividade', 'side');
        remove_meta_box('publicodiv', 'atividade', 'side');
        remove_meta_box('tagsdiv-tipo_atividade', 'atividade', 'side');

        add_meta_box(
            'api_np_atividade_dados',
            __('Cadastro da Atividade', 'api-no-parque'),
            array($this, 'render_activity_admin_form'),
            'atividade',
            'normal',
            'high'
        );
    }

    public function enqueue_uc_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && 'tipo_atividade' === $screen->taxonomy) {
            wp_enqueue_style(
                'api-np-fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
                array(),
                '6.5.2'
            );
            return;
        }

        if (!$screen || !in_array($screen->post_type, array('uc', 'atividade'), true)) {
            return;
        }

        $plugin_url = plugin_dir_url(__FILE__);

        wp_enqueue_media();

        wp_enqueue_style(
            'api-np-fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
            array(),
            '6.5.2'
        );

        wp_enqueue_style(
            'api-np-uc-admin',
            $plugin_url . 'assets/admin-uc-form.css',
            array('api-np-fontawesome'),
            $this->asset_version('assets/admin-uc-form.css')
        );

        wp_enqueue_script(
            'api-np-uc-admin',
            $plugin_url . 'assets/admin-uc-form.js',
            array(),
            $this->asset_version('assets/admin-uc-form.js'),
            true
        );

        wp_localize_script('api-np-uc-admin', 'ApiNpUcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'citiesNonce' => wp_create_nonce('um_dia_no_parque_elementor_nonce'),
            'labels' => array(
                'selectStateFirst' => __('Selecione o estado primeiro', 'api-no-parque'),
                'loading' => __('Carregando...', 'api-no-parque'),
                'selectCity' => __('Selecione a cidade', 'api-no-parque'),
                'noCity' => __('Nenhuma cidade encontrada', 'api-no-parque'),
                'loadError' => __('Erro ao carregar', 'api-no-parque'),
                'noActivities' => __('Nenhuma atividade selecionada.', 'api-no-parque'),
                'remove' => __('Remover', 'api-no-parque'),
            ),
        ));
    }

    public function render_uc_admin_form($post) {
        wp_nonce_field('uc_dados_nonce', 'uc_dados_nonce_field');

        $responsavel = get_post_meta($post->ID, '_uc_responsavel_atividade', true);
        $email = get_post_meta($post->ID, '_uc_email', true);
        $whatsapp = get_post_meta($post->ID, '_uc_whatsapp', true);
        $realizador = get_post_meta($post->ID, '_uc_realizador_atividade', true);
        $breve_descricao = get_post_meta($post->ID, '_uc_breve_descricao', true);
        $cep = get_post_meta($post->ID, '_uc_cep', true);
        $endereco = get_post_meta($post->ID, '_uc_endereco', true);
        $numero = get_post_meta($post->ID, '_uc_numero', true);
        $link_endereco = get_post_meta($post->ID, '_uc_link_endereco', true);
        $social = get_post_meta($post->ID, '_uc_social', true);
        $imagem_id = (int) get_post_meta($post->ID, '_uc_imagem', true);

        $city_data = $this->get_uc_admin_city_data($post->ID);
        $ufs = get_posts(array(
            'post_type' => 'uf',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $selected_activity_ids = get_post_meta($post->ID, '_uc_atividade_ids', true);
        $selected_activity_ids = is_array($selected_activity_ids) ? array_values(array_unique(array_map('intval', $selected_activity_ids))) : array();
        $activities = get_posts(array(
            'post_type' => 'atividade',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        $activities_by_id = array();
        foreach ($activities as $activity) {
            $activities_by_id[(int) $activity->ID] = $activity;
        }

        $carry_items = $this->get_carry_item_posts();
        $selected_carry_item_ids = get_post_meta($post->ID, self::META_UC_CARRY_ITEM_IDS, true);
        $selected_carry_item_ids = is_array($selected_carry_item_ids) ? array_values(array_unique(array_map('intval', $selected_carry_item_ids))) : array();

        $image_url = $imagem_id ? wp_get_attachment_image_url($imagem_id, 'medium') : '';
        ?>
        <div class="api-np-uc-form" data-api-np-uc-form>
            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Dados da Unidade de Conservacao', 'api-no-parque'); ?></h2>
                </div>

                <div class="api-np-uc-grid api-np-uc-grid--4">
                    <?php $this->render_uc_text_field('uc_responsavel', __('Responsavel pela Atividade', 'api-no-parque'), $responsavel, 'text', __('Nome do responsavel', 'api-no-parque')); ?>
                    <?php $this->render_uc_text_field('uc_email', __('Email', 'api-no-parque'), $email, 'email', 'contato@email.com'); ?>
                    <?php $this->render_uc_text_field('uc_whatsapp', __('WhatsApp', 'api-no-parque'), $whatsapp, 'tel', __('Telefone com DDD', 'api-no-parque')); ?>
                    <?php $this->render_uc_text_field('uc_realizador', __('Realizador da Atividade', 'api-no-parque'), $realizador, 'text', __('Instituicao responsavel', 'api-no-parque')); ?>

                    <label class="api-np-uc-field api-np-uc-field--full" for="uc_breve_descricao">
                        <span><?php esc_html_e('Breve descricao da UC', 'api-no-parque'); ?></span>
                        <textarea id="uc_breve_descricao" name="uc_breve_descricao" rows="4" placeholder="<?php esc_attr_e('Resumo curto usado no destaque da UC.', 'api-no-parque'); ?>"><?php echo esc_textarea($breve_descricao); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Dados de Localizacao', 'api-no-parque'); ?></h2>
                </div>

                <div class="api-np-uc-grid api-np-uc-grid--4">
                    <label class="api-np-uc-field" for="uc_uf_id">
                        <span><?php esc_html_e('Estado', 'api-no-parque'); ?></span>
                        <select id="uc_uf_id" name="uc_uf_id" data-api-np-state>
                            <option value=""><?php esc_html_e('Selecione o estado', 'api-no-parque'); ?></option>
                            <?php foreach ($ufs as $uf) :
                                $sigla = get_post_meta($uf->ID, '_uf_sigla', true);
                                ?>
                                <option value="<?php echo esc_attr($uf->ID); ?>" <?php selected((int) $city_data['uf_id'], (int) $uf->ID); ?>>
                                    <?php echo esc_html($sigla ? $uf->post_title . ' (' . $sigla . ')' : $uf->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="api-np-uc-field" for="uc_municipio">
                        <span><?php esc_html_e('Municipio', 'api-no-parque'); ?></span>
                        <select id="uc_municipio" name="uc_municipio" data-api-np-city data-current-city="<?php echo esc_attr($city_data['city']); ?>">
                            <option value=""><?php esc_html_e('Selecione o estado primeiro', 'api-no-parque'); ?></option>
                        </select>
                    </label>

                    <?php $this->render_uc_text_field('uc_cep', __('CEP', 'api-no-parque'), $cep, 'text', '00000-000'); ?>
                    <?php $this->render_uc_text_field('uc_numero', __('Numero', 'api-no-parque'), $numero, 'text', __('Numero ou S/N', 'api-no-parque')); ?>
                    <?php $this->render_uc_text_field('uc_endereco', __('Endereco', 'api-no-parque'), $endereco, 'text', __('Digite o endereco', 'api-no-parque'), 'api-np-uc-field--span-2'); ?>
                    <?php $this->render_uc_text_field('uc_link_endereco', __('Link do Endereco (Google Maps)', 'api-no-parque'), $link_endereco, 'url', 'https://', 'api-np-uc-field--span-2'); ?>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Midia e Redes', 'api-no-parque'); ?></h2>
                </div>

                <div class="api-np-uc-grid api-np-uc-grid--2">
                    <?php $this->render_uc_text_field('uc_social', __('Redes Sociais', 'api-no-parque'), $social, 'text', __('Instagram, Facebook, site...', 'api-no-parque')); ?>

                    <div class="api-np-uc-field api-np-image-field" data-api-np-image-field>
                        <span><?php esc_html_e('Imagem da UC', 'api-no-parque'); ?></span>
                        <input type="hidden" name="uc_imagem" value="<?php echo esc_attr((string) $imagem_id); ?>" data-api-np-image-id>
                        <div class="api-np-image-preview" data-api-np-image-preview>
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="">
                            <?php else : ?>
                                <em><?php esc_html_e('Nenhuma imagem selecionada.', 'api-no-parque'); ?></em>
                            <?php endif; ?>
                        </div>
                        <div class="api-np-image-actions">
                            <button type="button" class="button button-primary" data-api-np-select-image><?php esc_html_e('Selecionar imagem', 'api-no-parque'); ?></button>
                            <button type="button" class="button" data-api-np-remove-image <?php echo $imagem_id ? '' : 'hidden'; ?>><?php esc_html_e('Remover', 'api-no-parque'); ?></button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('O que levar', 'api-no-parque'); ?></h2>
                </div>

                <input type="hidden" name="uc_oque_levar_ids[]" value="">
                <div class="api-np-type-grid" role="group" aria-label="<?php esc_attr_e('O que levar para esta UC', 'api-no-parque'); ?>">
                    <?php foreach ($carry_items as $item) :
                        $icon = $this->normalize_fa_icon_class((string) get_post_meta($item->ID, self::META_CARRY_ITEM_ICON, true));
                        $is_selected = in_array((int) $item->ID, $selected_carry_item_ids, true);
                        ?>
                        <label class="api-np-type-card<?php echo $is_selected ? ' is-selected' : ''; ?>">
                            <input type="checkbox" name="uc_oque_levar_ids[]" value="<?php echo esc_attr((string) $item->ID); ?>" <?php checked($is_selected); ?>>
                            <span class="api-np-type-card__icon"><i class="fa-solid <?php echo esc_attr($icon); ?>" aria-hidden="true"></i></span>
                            <span class="api-np-type-card__name"><?php echo esc_html(get_the_title($item)); ?></span>
                            <span class="api-np-type-card__class"><?php echo esc_html($icon); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header api-np-uc-section__header--actions">
                    <h2><?php esc_html_e('Atividades da UC', 'api-no-parque'); ?></h2>
                    <button type="button" class="button button-primary" data-api-np-open-activity-modal><?php esc_html_e('Adicionar atividade', 'api-no-parque'); ?></button>
                </div>

                <input type="hidden" name="uc_atividade_ids[]" value="">
                <div class="api-np-activity-table-wrap">
                    <table class="widefat striped api-np-activity-table" data-api-np-selected-table>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Atividade', 'api-no-parque'); ?></th>
                                <th><?php esc_html_e('Data', 'api-no-parque'); ?></th>
                                <th><?php esc_html_e('Horario', 'api-no-parque'); ?></th>
                                <th><?php esc_html_e('Descricao', 'api-no-parque'); ?></th>
                                <th class="api-np-activity-actions"><?php esc_html_e('Acoes', 'api-no-parque'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-api-np-selected-body>
                            <?php foreach ($selected_activity_ids as $activity_id) :
                                if (!isset($activities_by_id[$activity_id])) {
                                    continue;
                                }
                                echo $this->render_activity_table_row($activities_by_id[$activity_id], true);
                            endforeach; ?>
                        </tbody>
                    </table>
                    <p class="api-np-activity-empty" data-api-np-selected-empty <?php echo empty($selected_activity_ids) ? '' : 'hidden'; ?>>
                        <?php esc_html_e('Nenhuma atividade selecionada.', 'api-no-parque'); ?>
                    </p>
                </div>
            </section>

            <div class="api-np-modal" data-api-np-activity-modal hidden>
                <div class="api-np-modal__backdrop" data-api-np-close-activity-modal></div>
                <section class="api-np-modal__panel" role="dialog" aria-modal="true" aria-labelledby="api-np-activity-modal-title">
                    <button type="button" class="api-np-modal__close" data-api-np-close-activity-modal aria-label="<?php esc_attr_e('Fechar', 'api-no-parque'); ?>">x</button>
                    <div class="api-np-modal__header">
                        <h2 id="api-np-activity-modal-title"><?php esc_html_e('Adicionar atividades', 'api-no-parque'); ?></h2>
                        <p><?php esc_html_e('Busque e marque as atividades que devem aparecer nesta UC.', 'api-no-parque'); ?></p>
                    </div>
                    <div class="api-np-modal__toolbar">
                        <input type="search" data-api-np-activity-search placeholder="<?php esc_attr_e('Buscar por nome, data ou descricao...', 'api-no-parque'); ?>">
                    </div>
                    <div class="api-np-modal__list" data-api-np-activity-list>
                        <?php foreach ($activities as $activity) :
                            $activity_id = (int) $activity->ID;
                            $checked = in_array($activity_id, $selected_activity_ids, true);
                            $data = $this->get_activity_admin_data($activity);
                            ?>
                            <label class="api-np-activity-option" data-api-np-activity-option data-search="<?php echo esc_attr(strtolower(remove_accents($data['title'] . ' ' . $data['date'] . ' ' . $data['time'] . ' ' . $data['description']))); ?>">
                                <input type="checkbox" value="<?php echo esc_attr((string) $activity_id); ?>" <?php checked($checked); ?> data-api-np-activity-checkbox>
                                <span class="api-np-activity-option__title"><?php echo esc_html($data['title']); ?></span>
                                <span><?php echo esc_html($data['date'] ?: '-'); ?></span>
                                <span><?php echo esc_html($data['time'] ?: '-'); ?></span>
                                <span><?php echo esc_html(wp_trim_words($data['description'], 14, '...')); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="api-np-modal__footer">
                        <button type="button" class="button" data-api-np-close-activity-modal><?php esc_html_e('Cancelar', 'api-no-parque'); ?></button>
                        <button type="button" class="button button-primary" data-api-np-apply-activities><?php esc_html_e('Aplicar selecao', 'api-no-parque'); ?></button>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }

    private function render_uc_text_field($name, $label, $value, $type = 'text', $placeholder = '', $class = '') {
        ?>
        <label class="api-np-uc-field <?php echo esc_attr($class); ?>" for="<?php echo esc_attr($name); ?>">
            <span><?php echo esc_html($label); ?></span>
            <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $value); ?>" placeholder="<?php echo esc_attr((string) $placeholder); ?>">
        </label>
        <?php
    }

    private function get_uc_admin_city_data(int $post_id) {
        $result = array(
            'city' => (string) get_post_meta($post_id, '_uc_municipio', true),
            'uf_id' => 0,
        );

        $city_terms = wp_get_object_terms($post_id, 'cidade', array('fields' => 'ids'));
        if (!is_wp_error($city_terms) && !empty($city_terms)) {
            $term = get_term((int) $city_terms[0], 'cidade');
            if ($term && !is_wp_error($term)) {
                $result['city'] = $term->name;
            }
            $result['uf_id'] = (int) get_term_meta((int) $city_terms[0], '_cidade_uf', true);
        }

        if (!$result['uf_id']) {
            $uf_ids = get_post_meta($post_id, '_uc_uf_ids', true);
            if (is_array($uf_ids) && !empty($uf_ids)) {
                $result['uf_id'] = (int) reset($uf_ids);
            } else {
                $result['uf_id'] = (int) get_post_meta($post_id, '_uc_uf_id', true);
            }
        }

        return $result;
    }

    private function render_activity_table_row($activity, $with_input = false) {
        $data = $this->get_activity_admin_data($activity);

        ob_start();
        ?>
        <tr data-api-np-selected-activity="<?php echo esc_attr((string) $data['id']); ?>">
            <td>
                <?php if ($with_input) : ?>
                    <input type="hidden" name="uc_atividade_ids[]" value="<?php echo esc_attr((string) $data['id']); ?>">
                <?php endif; ?>
                <strong><?php echo esc_html($data['title']); ?></strong>
                <small>#<?php echo esc_html((string) $data['id']); ?></small>
            </td>
            <td><?php echo esc_html($data['date'] ?: '-'); ?></td>
            <td><?php echo esc_html($data['time'] ?: '-'); ?></td>
            <td><?php echo esc_html(wp_trim_words($data['description'], 18, '...')); ?></td>
            <td class="api-np-activity-actions">
                <button type="button" class="button-link-delete" data-api-np-remove-activity="<?php echo esc_attr((string) $data['id']); ?>"><?php esc_html_e('Remover', 'api-no-parque'); ?></button>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function get_activity_admin_data($activity) {
        $post_id = (int) $activity->ID;
        $description = get_post_meta($post_id, '_atividade_descricao', true);
        if ('' === trim((string) $description)) {
            $description = $activity->post_excerpt ?: $activity->post_content;
        }

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'date' => (string) get_post_meta($post_id, '_atividade_data', true),
            'time' => (string) get_post_meta($post_id, '_atividade_horario', true),
            'description' => wp_strip_all_tags((string) $description),
        );
    }

    public function render_activity_admin_form($post) {
        wp_nonce_field('atividade_save', 'atividade_nonce');

        $data = get_post_meta($post->ID, '_atividade_data', true);
        $horario = get_post_meta($post->ID, '_atividade_horario', true);
        $descricao = get_post_meta($post->ID, '_atividade_descricao', true);
        $ativo = get_post_meta($post->ID, '_atividade_ativo', true);
        $ativo = '' === $ativo ? '1' : $ativo;
        $selected_uc_id = $this->get_activity_uc_id((int) $post->ID);
        $ucs = get_posts(array(
            'post_type' => 'uc',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $dif_ids = $this->get_post_term_ids($post->ID, 'dificuldade');
        $pub_ids = $this->get_post_term_ids($post->ID, 'publico');
        $tipo_ids = $this->get_post_term_ids($post->ID, 'tipo_atividade');

        $dificuldades = get_terms(array('taxonomy' => 'dificuldade', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        $publicos = get_terms(array('taxonomy' => 'publico', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        $tipos = get_terms(array(
            'taxonomy' => 'tipo_atividade',
            'hide_empty' => false,
            'meta_key' => '_api_np_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ));
        if (is_wp_error($tipos)) {
            $tipos = array();
        }
        ?>
        <div class="api-np-uc-form api-np-activity-form" data-api-np-activity-form>
            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Dados da Atividade', 'api-no-parque'); ?></h2>
                </div>

                <div class="api-np-uc-grid api-np-uc-grid--4">
                    <label class="api-np-uc-field api-np-uc-field--span-2" for="atividade_uc_id">
                        <span><?php esc_html_e('Unidade de Conservacao', 'api-no-parque'); ?></span>
                        <select id="atividade_uc_id" name="atividade_uc_id">
                            <option value=""><?php esc_html_e('Selecione a UC desta atividade', 'api-no-parque'); ?></option>
                            <?php foreach ($ucs as $uc) : ?>
                                <option value="<?php echo esc_attr((string) $uc->ID); ?>" <?php selected($selected_uc_id, (int) $uc->ID); ?>>
                                    <?php echo esc_html(get_the_title($uc)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php $this->render_uc_text_field('atividade_data', __('Data de Realizacao', 'api-no-parque'), $data, 'text', __('Ex: 19 de julho de 2026', 'api-no-parque')); ?>
                    <?php $this->render_uc_text_field('atividade_horario', __('Horario', 'api-no-parque'), $horario, 'text', __('Ex: 08:00 as 12:00', 'api-no-parque')); ?>

                    <label class="api-np-uc-field" for="atividade_ativo">
                        <span><?php esc_html_e('Status', 'api-no-parque'); ?></span>
                        <select id="atividade_ativo" name="atividade_ativo">
                            <option value="1" <?php selected($ativo, '1'); ?>><?php esc_html_e('Ativa', 'api-no-parque'); ?></option>
                            <option value="0" <?php selected($ativo, '0'); ?>><?php esc_html_e('Oculta no site', 'api-no-parque'); ?></option>
                        </select>
                    </label>

                    <label class="api-np-uc-field api-np-uc-field--full" for="atividade_descricao">
                        <span><?php esc_html_e('Descricao da atividade', 'api-no-parque'); ?></span>
                        <textarea id="atividade_descricao" name="atividade_descricao" rows="5" placeholder="<?php esc_attr_e('Descreva a atividade que sera exibida no site.', 'api-no-parque'); ?>"><?php echo esc_textarea($descricao); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Classificacao', 'api-no-parque'); ?></h2>
                </div>

                <div class="api-np-uc-grid api-np-uc-grid--2">
                    <label class="api-np-uc-field" for="atividade_dificuldade">
                        <span><?php esc_html_e('Dificuldade', 'api-no-parque'); ?></span>
                        <select id="atividade_dificuldade" name="atividade_dificuldade[]" multiple size="<?php echo esc_attr((string) $this->select_size($dificuldades)); ?>">
                            <?php foreach ($this->safe_terms($dificuldades) as $term) : ?>
                                <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected(in_array((int) $term->term_id, $dif_ids, true)); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="api-np-uc-field" for="atividade_publico">
                        <span><?php esc_html_e('Publico', 'api-no-parque'); ?></span>
                        <select id="atividade_publico" name="atividade_publico[]" multiple size="<?php echo esc_attr((string) $this->select_size($publicos)); ?>">
                            <?php foreach ($this->safe_terms($publicos) as $term) : ?>
                                <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected(in_array((int) $term->term_id, $pub_ids, true)); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

            <section class="api-np-uc-section">
                <div class="api-np-uc-section__header">
                    <h2><?php esc_html_e('Tipo de Atividade', 'api-no-parque'); ?></h2>
                </div>

                <input type="hidden" name="atividade_tipo[]" value="">
                <div class="api-np-type-grid" role="group" aria-label="<?php esc_attr_e('Tipos de atividade', 'api-no-parque'); ?>">
                    <?php foreach ($this->safe_terms($tipos) as $term) :
                        $icon = get_term_meta($term->term_id, self::META_ACTIVITY_TYPE_ICON, true) ?: 'fa-circle';
                        $is_selected = in_array((int) $term->term_id, $tipo_ids, true);
                        ?>
                        <label class="api-np-type-card<?php echo $is_selected ? ' is-selected' : ''; ?>">
                            <input type="checkbox" name="atividade_tipo[]" value="<?php echo esc_attr((string) $term->term_id); ?>" <?php checked($is_selected); ?>>
                            <span class="api-np-type-card__icon"><i class="fa-solid <?php echo esc_attr($icon); ?>" aria-hidden="true"></i></span>
                            <span class="api-np-type-card__name"><?php echo esc_html($term->name); ?></span>
                            <span class="api-np-type-card__class"><?php echo esc_html($icon); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php
    }

    public function save_activity_uc_relation($post_id, $post) {
        if (!isset($_POST['atividade_nonce']) || !wp_verify_nonce(sanitize_key($_POST['atividade_nonce']), 'atividade_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['atividade_uc_id'])) {
            return;
        }

        $uc_id = absint(wp_unslash($_POST['atividade_uc_id']));
        if ($uc_id > 0 && 'uc' !== get_post_type($uc_id)) {
            $uc_id = 0;
        }

        $this->sync_activity_to_single_uc((int) $post_id, $uc_id);
    }

    public function sync_uc_activity_relations_from_uc_form($post_id, $post) {
        if (!isset($_POST['uc_dados_nonce_field']) || !wp_verify_nonce(sanitize_key($_POST['uc_dados_nonce_field']), 'uc_dados_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['uc_atividade_ids'])) {
            return;
        }

        $selected_ids = array_values(array_unique(array_filter(array_map('intval', (array) wp_unslash($_POST['uc_atividade_ids'])))));
        $current_ids = $this->get_uc_activity_ids((int) $post_id);

        foreach (array_diff($current_ids, $selected_ids) as $activity_id) {
            if ((int) get_post_meta((int) $activity_id, self::META_ACTIVITY_UC_ID, true) === (int) $post_id) {
                delete_post_meta((int) $activity_id, self::META_ACTIVITY_UC_ID);
            }
        }

        foreach ($selected_ids as $activity_id) {
            $this->sync_activity_to_single_uc((int) $activity_id, (int) $post_id);
        }

        if (!empty($selected_ids)) {
            update_post_meta((int) $post_id, '_uc_atividade_ids', $selected_ids);
        } else {
            delete_post_meta((int) $post_id, '_uc_atividade_ids');
        }
    }

    private function sync_activity_to_single_uc(int $activity_id, int $uc_id) {
        if ($activity_id <= 0 || 'atividade' !== get_post_type($activity_id)) {
            return;
        }

        if ($uc_id > 0 && 'uc' !== get_post_type($uc_id)) {
            $uc_id = 0;
        }

        $ucs = get_posts(array(
            'post_type' => 'uc',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($ucs as $candidate_uc_id) {
            $ids = get_post_meta((int) $candidate_uc_id, '_uc_atividade_ids', true);
            $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : array();

            if (!in_array($activity_id, $ids, true)) {
                continue;
            }

            $ids = array_values(array_diff($ids, array($activity_id)));
            if (!empty($ids)) {
                update_post_meta((int) $candidate_uc_id, '_uc_atividade_ids', $ids);
            } else {
                delete_post_meta((int) $candidate_uc_id, '_uc_atividade_ids');
            }
        }

        if ($uc_id > 0) {
            update_post_meta($activity_id, self::META_ACTIVITY_UC_ID, (string) $uc_id);

            $ids = get_post_meta($uc_id, '_uc_atividade_ids', true);
            $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : array();
            $ids[] = $activity_id;
            update_post_meta($uc_id, '_uc_atividade_ids', array_values(array_unique($ids)));
        } else {
            delete_post_meta($activity_id, self::META_ACTIVITY_UC_ID);
        }
    }

    private function get_activity_uc_id(int $activity_id) {
        $uc_id = (int) get_post_meta($activity_id, self::META_ACTIVITY_UC_ID, true);
        if ($uc_id > 0 && 'uc' === get_post_type($uc_id)) {
            return $uc_id;
        }

        $ucs = get_posts(array(
            'post_type' => 'uc',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($ucs as $candidate_uc_id) {
            $ids = get_post_meta((int) $candidate_uc_id, '_uc_atividade_ids', true);
            $ids = is_array($ids) ? array_map('intval', $ids) : array();

            if (in_array($activity_id, $ids, true)) {
                return (int) $candidate_uc_id;
            }
        }

        return 0;
    }

    private function get_post_term_ids(int $post_id, string $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        return $terms && !is_wp_error($terms) ? array_map('intval', wp_list_pluck($terms, 'term_id')) : array();
    }

    private function safe_terms($terms) {
        return is_wp_error($terms) || !is_array($terms) ? array() : $terms;
    }

    private function select_size($terms) {
        $count = count($this->safe_terms($terms));
        return max(3, min(6, $count));
    }

    private function get_activity_types_catalog() {
        return array(
            array('name' => 'Trilha', 'icon' => 'fa-person-hiking'),
            array('name' => 'Cachoeira', 'icon' => 'fa-water'),
            array('name' => 'Mutirao de limpeza', 'icon' => 'fa-broom'),
            array('name' => 'Plantio', 'icon' => 'fa-seedling'),
            array('name' => 'Observacao de fauna', 'icon' => 'fa-paw'),
            array('name' => 'Caverna', 'icon' => 'fa-mountain'),
            array('name' => 'Educacao ambiental', 'icon' => 'fa-book-open'),
            array('name' => 'Acampamento', 'icon' => 'fa-campground'),
            array('name' => 'Atividade noturna', 'icon' => 'fa-moon'),
            array('name' => 'Ciclismo', 'icon' => 'fa-person-biking'),
            array('name' => 'Oficina', 'icon' => 'fa-screwdriver-wrench'),
            array('name' => 'Yoga', 'icon' => 'fa-spa'),
            array('name' => 'Cinema', 'icon' => 'fa-film'),
            array('name' => 'Sarau', 'icon' => 'fa-microphone'),
            array('name' => 'Exposicao', 'icon' => 'fa-image'),
            array('name' => 'Feira', 'icon' => 'fa-store'),
            array('name' => 'Programacao Cultural', 'icon' => 'fa-masks-theater'),
            array('name' => 'Teatro', 'icon' => 'fa-masks-theater'),
            array('name' => 'Piquenique', 'icon' => 'fa-basket-shopping'),
            array('name' => 'Observacao de Aves', 'icon' => 'fa-dove'),
            array('name' => 'Rapel', 'icon' => 'fa-link'),
            array('name' => 'Banho de Floresta', 'icon' => 'fa-tree'),
            array('name' => 'Meditacao', 'icon' => 'fa-om'),
            array('name' => 'Canoagem', 'icon' => 'fa-water'),
            array('name' => 'Tirolesa', 'icon' => 'fa-cable-car'),
            array('name' => 'Contacao de Historias', 'icon' => 'fa-book'),
        );
    }

    public function maybe_sync_activity_types() {
        if (!taxonomy_exists('tipo_atividade')) {
            return;
        }

        if (get_option('api_np_activity_types_version') === self::ACTIVITY_TYPES_VERSION) {
            return;
        }

        $lock_key = 'api_np_activity_types_syncing_' . md5(self::ACTIVITY_TYPES_VERSION);
        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, MINUTE_IN_SECONDS * 5);
        $this->sync_activity_types();
        update_option('api_np_activity_types_version', self::ACTIVITY_TYPES_VERSION, false);
        delete_transient($lock_key);
    }

    private function sync_activity_types() {
        $activity_type_sources = $this->collect_activity_type_sources();

        $terms = get_terms(array(
            'taxonomy' => 'tipo_atividade',
            'hide_empty' => false,
            'fields' => 'ids',
        ));

        if (!is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                wp_delete_term((int) $term_id, 'tipo_atividade');
            }
        }

        foreach ($this->get_activity_types_catalog() as $index => $item) {
            $result = wp_insert_term($item['name'], 'tipo_atividade', array(
                'slug' => sanitize_title($item['name']),
            ));

            if (is_wp_error($result) || empty($result['term_id'])) {
                $existing = term_exists($item['name'], 'tipo_atividade');
                $term_id = is_array($existing) && !empty($existing['term_id']) ? (int) $existing['term_id'] : 0;
            } else {
                $term_id = (int) $result['term_id'];
            }

            if ($term_id) {
                update_term_meta($term_id, self::META_ACTIVITY_TYPE_ICON, $item['icon']);
                update_term_meta($term_id, '_api_np_order', $index + 1);
            }
        }

        foreach ($activity_type_sources as $post_id => $source) {
            $this->assign_terms((int) $post_id, 'tipo_atividade', $source);
        }
    }

    private function collect_activity_type_sources() {
        $posts = get_posts(array(
            'post_type' => 'atividade',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $sources = array();
        foreach ($posts as $post_id) {
            $terms = wp_get_post_terms((int) $post_id, 'tipo_atividade', array('fields' => 'names'));
            $parts = !is_wp_error($terms) ? $terms : array();
            $parts[] = get_the_title((int) $post_id);
            $parts[] = get_post_meta((int) $post_id, '_atividade_descricao', true);
            $sources[(int) $post_id] = implode(' ', array_filter(array_map('strval', $parts)));
        }

        return $sources;
    }

    public function maybe_normalize_activity_classifications() {
        if (!taxonomy_exists('dificuldade') || !taxonomy_exists('publico') || !post_type_exists('atividade')) {
            return;
        }

        if (get_option('api_np_activity_classifications_version') === self::ACTIVITY_CLASSIFICATIONS_VERSION) {
            return;
        }

        $lock_key = 'api_np_activity_classifications_syncing_' . md5(self::ACTIVITY_CLASSIFICATIONS_VERSION);
        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, MINUTE_IN_SECONDS * 5);
        $stats = $this->normalize_activity_classifications();
        update_option('api_np_activity_classifications_version', self::ACTIVITY_CLASSIFICATIONS_VERSION, false);
        update_option('api_np_activity_classifications_last_stats', $stats, false);
        delete_transient($lock_key);
    }

    private function normalize_activity_classifications() {
        $sources = $this->collect_activity_classification_sources();
        $difficulty_ids = $this->reset_taxonomy_terms('dificuldade', $this->get_difficulty_catalog());
        $public_ids = $this->reset_taxonomy_terms('publico', $this->get_public_catalog());
        $stats = array(
            'activities' => 0,
            'difficulty' => array(),
            'publico' => array(),
        );

        foreach ($sources as $post_id => $source) {
            $difficulty = $this->classify_difficulty($source['difficulty']);
            $public = $this->classify_public($source['public']);

            if ($difficulty && isset($difficulty_ids[$difficulty])) {
                wp_set_object_terms((int) $post_id, array($difficulty_ids[$difficulty]), 'dificuldade', false);
                $stats['difficulty'][$difficulty] = ($stats['difficulty'][$difficulty] ?? 0) + 1;
            }

            if ($public && isset($public_ids[$public])) {
                wp_set_object_terms((int) $post_id, array($public_ids[$public]), 'publico', false);
                $stats['publico'][$public] = ($stats['publico'][$public] ?? 0) + 1;
            }

            $stats['activities']++;
        }

        return $stats;
    }

    private function collect_activity_classification_sources() {
        $posts = get_posts(array(
            'post_type' => 'atividade',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        $sources = array();
        foreach ($posts as $post_id) {
            $difficulty_terms = wp_get_post_terms((int) $post_id, 'dificuldade', array('fields' => 'names'));
            $public_terms = wp_get_post_terms((int) $post_id, 'publico', array('fields' => 'names'));
            $title = get_the_title((int) $post_id);
            $description = get_post_meta((int) $post_id, '_atividade_descricao', true);

            $sources[(int) $post_id] = array(
                'difficulty' => implode(' ', array_filter(array_merge(
                    !is_wp_error($difficulty_terms) ? $difficulty_terms : array(),
                    array($title, $description)
                ))),
                'public' => implode(' ', array_filter(array_merge(
                    !is_wp_error($public_terms) ? $public_terms : array(),
                    array($title, $description)
                ))),
            );
        }

        return $sources;
    }

    private function reset_taxonomy_terms(string $taxonomy, array $catalog) {
        $existing = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids',
        ));

        if (!is_wp_error($existing)) {
            foreach ($existing as $term_id) {
                wp_delete_term((int) $term_id, $taxonomy);
            }
        }

        $term_ids = array();
        foreach ($catalog as $index => $item) {
            $result = wp_insert_term($item['name'], $taxonomy, array(
                'slug' => sanitize_title($item['name']),
            ));

            if (is_wp_error($result) || empty($result['term_id'])) {
                $existing_term = term_exists($item['name'], $taxonomy);
                $term_id = is_array($existing_term) && !empty($existing_term['term_id']) ? (int) $existing_term['term_id'] : 0;
            } else {
                $term_id = (int) $result['term_id'];
            }

            if ($term_id) {
                $term_ids[$item['name']] = $term_id;
                update_term_meta($term_id, '_api_np_order', $index + 1);
                if (isset($item['level'])) {
                    update_term_meta($term_id, '_dificuldade_level', (string) $item['level']);
                }
            }
        }

        return $term_ids;
    }

    private function get_difficulty_catalog() {
        return array(
            array('name' => 'Leve', 'level' => 1),
            array('name' => 'Moderada', 'level' => 5),
            array('name' => $this->decode_label('Avan&ccedil;ado'), 'level' => 9),
            array('name' => $this->decode_label('N&atilde;o informado'), 'level' => 0),
        );
    }

    private function get_public_catalog() {
        return array(
            array('name' => 'Infantil'),
            array('name' => 'Adulto'),
            array('name' => 'Geral'),
        );
    }

    private function classify_difficulty(string $source) {
        $needle = $this->normalize_key($source);

        if ('' === $needle || false !== strpos($needle, 'confirmar') || false !== strpos($needle, 'nao informado') || false !== strpos($needle, 'a definir') || false !== strpos($needle, 'organizador')) {
            return $this->decode_label('N&atilde;o informado');
        }

        if (false !== strpos($needle, 'avanc') || false !== strpos($needle, 'dificil') || false !== strpos($needle, 'superior') || false !== strpos($needle, 'exigente')) {
            return $this->decode_label('Avan&ccedil;ado');
        }

        if (false !== strpos($needle, 'moderad') || false !== strpos($needle, 'media') || false !== strpos($needle, 'medio')) {
            return 'Moderada';
        }

        if (false !== strpos($needle, 'leve') || false !== strpos($needle, 'baixa') || false !== strpos($needle, 'facil') || false !== strpos($needle, 'iniciante')) {
            return 'Leve';
        }

        return $this->decode_label('N&atilde;o informado');
    }

    private function classify_public(string $source) {
        $needle = $this->normalize_key($source);

        if ('' === $needle) {
            return 'Geral';
        }

        $general_words = array('geral', 'todos', 'todas', 'comunidade', 'aberto', 'aberta', 'publico', 'familia', 'familias', 'visitantes', 'moradores', 'populacao', 'turistas', 'tradicional', 'tradicionais', 'local', 'sem restricao');
        foreach ($general_words as $word) {
            if (false !== strpos($needle, $word)) {
                return 'Geral';
            }
        }

        $child_words = array('infantil', 'crianca', 'criancas', 'kids', 'aluno', 'alunos', 'escola', 'escolar', 'adolescente', 'adolescentes');
        foreach ($child_words as $word) {
            if (false !== strpos($needle, $word)) {
                return 'Infantil';
            }
        }

        $adult_words = array('adulto', 'adultos', 'jovem', 'jovens', 'autoridade', 'autoridades', 'conselho', 'parceiro', 'parceiros', 'institucional', 'institucionais', 'experiencia', 'responsavel', 'responsaveis', 'maior');
        foreach ($adult_words as $word) {
            if (false !== strpos($needle, $word)) {
                return 'Adulto';
            }
        }

        return 'Geral';
    }

    public function maybe_sync_carry_items() {
        if (!post_type_exists('oque_levar')) {
            return;
        }

        if (get_option('api_np_carry_items_version') === self::CARRY_ITEMS_VERSION) {
            return;
        }

        $lock_key = 'api_np_carry_items_syncing_' . md5(self::CARRY_ITEMS_VERSION);
        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, MINUTE_IN_SECONDS * 5);
        $stats = $this->sync_carry_items();
        update_option('api_np_carry_items_version', self::CARRY_ITEMS_VERSION, false);
        update_option('api_np_carry_items_last_stats', $stats, false);
        delete_transient($lock_key);
    }

    private function sync_carry_items() {
        $existing = get_posts(array(
            'post_type' => 'oque_levar',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));

        foreach ($existing as $post_id) {
            wp_delete_post((int) $post_id, true);
        }

        $created = 0;
        foreach ($this->get_carry_items_catalog() as $index => $item) {
            $post_id = wp_insert_post(array(
                'post_type' => 'oque_levar',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field($item['name']),
                'menu_order' => $index + 1,
            ), true);

            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }

            update_post_meta((int) $post_id, self::META_CARRY_ITEM_ICON, sanitize_text_field($item['icon']));
            update_post_meta((int) $post_id, '_api_np_order', (string) ($index + 1));
            $created++;
        }

        return array('created' => $created);
    }

    private function get_carry_items_catalog() {
        return array(
            array('name' => $this->decode_label('Cal&ccedil;ado adequado'), 'icon' => 'fa-shoe-prints'),
            array('name' => $this->decode_label('Roupa confort&aacute;vel'), 'icon' => 'fa-shirt'),
            array('name' => 'Repelente', 'icon' => 'fa-spray-can-sparkles'),
            array('name' => $this->decode_label('&Aacute;gua'), 'icon' => 'fa-bottle-water'),
            array('name' => 'Perneira', 'icon' => 'fa-socks'),
            array('name' => 'Lanterna', 'icon' => 'fa-lightbulb'),
            array('name' => 'Caiaque ou stand up paddle', 'icon' => 'fa-person-swimming'),
            array('name' => 'Colete salva-vidas', 'icon' => 'fa-life-ring'),
            array('name' => 'Protetor solar', 'icon' => 'fa-sun'),
            array('name' => $this->decode_label('Chap&eacute;u/bon&eacute;'), 'icon' => 'fa-hat-cowboy'),
            array('name' => $this->decode_label('Roupas com prote&ccedil;&atilde;o UV'), 'icon' => 'fa-shirt'),
            array('name' => 'Roupa de banho', 'icon' => 'fa-shirt'),
            array('name' => 'Kit Primeiros Socorros', 'icon' => 'fa-kit-medical'),
            array('name' => 'Lanche', 'icon' => 'fa-apple-whole'),
            array('name' => 'Roupa extra', 'icon' => 'fa-shirt'),
        );
    }

    private function get_carry_item_posts(array $include = array()) {
        $args = array(
            'post_type' => 'oque_levar',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => array(
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ),
            'order' => 'ASC',
        );

        if (!empty($include)) {
            $args['post__in'] = array_values(array_unique(array_map('intval', $include)));
            $args['orderby'] = 'post__in';
        }

        return get_posts($args);
    }

    public function save_uc_carry_items($post_id, $post) {
        if (!isset($_POST['uc_dados_nonce_field']) || !wp_verify_nonce(sanitize_key($_POST['uc_dados_nonce_field']), 'uc_dados_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['uc_oque_levar_ids'])) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) wp_unslash($_POST['uc_oque_levar_ids'])))));
        $ids = array_values(array_filter($ids, function ($id) {
            return 'oque_levar' === get_post_type((int) $id);
        }));

        if (!empty($ids)) {
            update_post_meta((int) $post_id, self::META_UC_CARRY_ITEM_IDS, $ids);
        } else {
            delete_post_meta((int) $post_id, self::META_UC_CARRY_ITEM_IDS);
        }
    }

    public function activity_type_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            if ('cb' === $key) {
                $new[$key] = $label;
                $new['api_np_icon'] = __('Icone', 'api-no-parque');
                continue;
            }
            $new[$key] = $label;
        }

        return $new;
    }

    public function activity_type_column_content($content, $column_name, $term_id) {
        if ('api_np_icon' !== $column_name) {
            return $content;
        }

        $icon = get_term_meta((int) $term_id, self::META_ACTIVITY_TYPE_ICON, true);
        if (!$icon) {
            return '&mdash;';
        }

        return '<i class="fa-solid ' . esc_attr($icon) . '" aria-hidden="true"></i> <code>' . esc_html($icon) . '</code>';
    }

    public function handle_admin_import() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissao.', 'api-no-parque'));
        }

        check_admin_referer('api_np_import_json', 'api_np_nonce');

        $redirect = admin_url('tools.php?page=api-no-parque');

        if (empty($_FILES['api_np_json']['tmp_name'])) {
            set_transient('api_np_last_import_result', array(
                'ok' => false,
                'message' => __('Nenhum arquivo enviado.', 'api-no-parque'),
            ), 60);
            wp_safe_redirect($redirect);
            exit;
        }

        $raw = file_get_contents($_FILES['api_np_json']['tmp_name']);
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            set_transient('api_np_last_import_result', array(
                'ok' => false,
                'message' => __('JSON invalido.', 'api-no-parque'),
            ), 60);
            wp_safe_redirect($redirect);
            exit;
        }

        $dry_run = !empty($_POST['dry_run']);
        $result = $this->import_payload($payload, $dry_run);

        set_transient('api_np_last_import_result', array(
            'ok' => empty($result['errors']),
            'message' => $dry_run ? __('Simulacao concluida.', 'api-no-parque') : __('Importacao concluida.', 'api-no-parque'),
            'stats' => $result,
        ), 60);

        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_admin_purge() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissao.', 'api-no-parque'));
        }

        check_admin_referer('api_np_purge_data', 'api_np_purge_nonce');

        $redirect = admin_url('tools.php?page=api-no-parque');
        $dry_run = !empty($_POST['dry_run']);
        $delete_terms = !empty($_POST['delete_terms']);
        $confirm = isset($_POST['confirm']) ? sanitize_text_field(wp_unslash($_POST['confirm'])) : '';

        $result = $this->purge_data(array(
            'dry_run' => $dry_run,
            'delete_terms' => $delete_terms,
            'confirm' => $confirm,
        ));

        set_transient('api_np_last_import_result', array(
            'ok' => empty($result['errors']),
            'message' => $dry_run ? __('Simulacao de limpeza concluida.', 'api-no-parque') : __('Limpeza concluida.', 'api-no-parque'),
            'stats' => $result,
        ), 60);

        wp_safe_redirect($redirect);
        exit;
    }

    public function rest_status() {
        return rest_ensure_response(array(
            'plugin' => 'api-no-parque',
            'version' => self::VERSION,
            'legacy_available' => $this->legacy_available(),
            'counts' => array(
                'uc' => $this->count_posts('uc'),
                'atividade' => $this->count_posts('atividade'),
                'uf' => $this->count_posts('uf'),
            ),
            'endpoints' => array(
                'map' => rest_url(self::REST_NAMESPACE . '/map'),
                'list' => rest_url(self::REST_NAMESPACE . '/list'),
                'ucs' => rest_url(self::REST_NAMESPACE . '/ucs'),
                'import' => rest_url(self::REST_NAMESPACE . '/import'),
            ),
        ));
    }

    public function rest_collection(WP_REST_Request $request) {
        $args = $this->collection_query_args($request);
        $query = new WP_Query($args);
        $items = array();

        foreach ($query->posts as $post) {
            $item = $this->format_uc_item($post->ID);
            if ($item) {
                $items[] = $item;
            }
        }

        return rest_ensure_response(array(
            'source' => 'wordpress',
            'generated_at' => current_time('mysql'),
            'total_items' => count($items),
            'items' => $items,
        ));
    }

    public function rest_get_uc(WP_REST_Request $request) {
        $post_id = absint($request['id']);

        if (!$this->is_uc($post_id)) {
            return new WP_Error('api_np_not_found', __('UC nao encontrada.', 'api-no-parque'), array('status' => 404));
        }

        return rest_ensure_response($this->format_uc_item($post_id));
    }

    public function rest_create_uc(WP_REST_Request $request) {
        $item = $this->normalise_input_item($request->get_json_params());

        if (empty($item['name'])) {
            return new WP_Error('api_np_invalid_uc', __('Nome da UC obrigatorio.', 'api-no-parque'), array('status' => 400));
        }

        $result = $this->import_item($item, false);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($this->format_uc_item($result));
    }

    public function rest_update_uc(WP_REST_Request $request) {
        $post_id = absint($request['id']);

        if (!$this->is_uc($post_id)) {
            return new WP_Error('api_np_not_found', __('UC nao encontrada.', 'api-no-parque'), array('status' => 404));
        }

        $raw = $request->get_json_params();
        $item = $this->normalise_input_item($raw);
        $item['wp_id'] = $post_id;
        $item['partial_update'] = true;

        if (empty($item['name'])) {
            $item['name'] = get_the_title($post_id);
        }

        $result = $this->import_item($item, false);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($this->format_uc_item($result));
    }

    public function rest_delete_uc(WP_REST_Request $request) {
        $post_id = absint($request['id']);

        if (!$this->is_uc($post_id)) {
            return new WP_Error('api_np_not_found', __('UC nao encontrada.', 'api-no-parque'), array('status' => 404));
        }

        $previous = $this->format_uc_item($post_id);
        $force = (bool) $request->get_param('force');
        $deleted = wp_delete_post($post_id, $force);

        if (!$deleted) {
            return new WP_Error('api_np_delete_failed', __('Nao foi possivel remover a UC.', 'api-no-parque'), array('status' => 500));
        }

        return rest_ensure_response(array(
            'deleted' => true,
            'previous' => $previous,
        ));
    }

    public function rest_import(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        $dry_run = (bool) $request->get_param('dry_run');

        if (!is_array($payload)) {
            return new WP_Error('api_np_invalid_json', __('Payload JSON invalido.', 'api-no-parque'), array('status' => 400));
        }

        return rest_ensure_response($this->import_payload($payload, $dry_run));
    }

    public function rest_purge(WP_REST_Request $request) {
        return rest_ensure_response($this->purge_data(array(
            'dry_run' => (bool) $request->get_param('dry_run'),
            'delete_terms' => (bool) $request->get_param('delete_terms'),
            'confirm' => sanitize_text_field((string) $request->get_param('confirm')),
        )));
    }

    private function purge_data(array $options) {
        $dry_run = !empty($options['dry_run']);
        $delete_terms = !empty($options['delete_terms']);
        $confirm = isset($options['confirm']) ? (string) $options['confirm'] : '';
        $stats = array(
            'dry_run' => $dry_run,
            'delete_terms' => $delete_terms,
            'uc_deleted' => 0,
            'atividade_deleted' => 0,
            'terms_deleted' => 0,
            'errors' => array(),
        );

        if (!$dry_run && 'LIMPAR BASE' !== $confirm) {
            $stats['errors'][] = __('Confirmacao invalida. Digite LIMPAR BASE.', 'api-no-parque');
            return $stats;
        }

        foreach (array('uc' => 'uc_deleted', 'atividade' => 'atividade_deleted') as $post_type => $stat_key) {
            $ids = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ));

            foreach ($ids as $post_id) {
                if ($dry_run) {
                    $stats[$stat_key]++;
                    continue;
                }

                $deleted = wp_delete_post((int) $post_id, true);
                if ($deleted) {
                    $stats[$stat_key]++;
                } else {
                    $stats['errors'][] = sprintf('Falha ao apagar %s #%d.', $post_type, $post_id);
                }
            }
        }

        if ($delete_terms) {
            foreach (array('bioma', 'cidade', 'dificuldade', 'publico', 'tipo_atividade') as $taxonomy) {
                if (!taxonomy_exists($taxonomy)) {
                    continue;
                }

                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'fields' => 'ids',
                ));

                if (is_wp_error($terms)) {
                    $stats['errors'][] = $terms->get_error_message();
                    continue;
                }

                foreach ($terms as $term_id) {
                    if ($dry_run) {
                        $stats['terms_deleted']++;
                        continue;
                    }

                    $deleted = wp_delete_term((int) $term_id, $taxonomy);
                    if ($deleted && !is_wp_error($deleted)) {
                        $stats['terms_deleted']++;
                    } elseif (is_wp_error($deleted)) {
                        $stats['errors'][] = $deleted->get_error_message();
                    }
                }
            }
        }

        return $stats;
    }

    private function collection_query_args(WP_REST_Request $request) {
        $args = array(
            'post_type' => 'uc',
            'post_status' => 'publish',
            'posts_per_page' => min(500, max(1, absint($request->get_param('per_page') ?: 500))),
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        );

        $search = sanitize_text_field((string) $request->get_param('search'));
        if ($search) {
            $args['s'] = $search;
        }

        $slug = sanitize_title((string) $request->get_param('slug'));
        if ($slug) {
            $args['name'] = $slug;
            $args['posts_per_page'] = 1;
        }

        $tax_query = array();

        $biome = sanitize_text_field((string) ($request->get_param('biome') ?: $request->get_param('bioma')));
        if ($biome) {
            $tax_query[] = array(
                'taxonomy' => 'bioma',
                'field' => 'slug',
                'terms' => $biome,
            );
        }

        $city = sanitize_text_field((string) ($request->get_param('city') ?: $request->get_param('cidade')));
        if ($city) {
            $tax_query[] = array(
                'taxonomy' => 'cidade',
                'field' => 'name',
                'terms' => $city,
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    private function import_payload(array $payload, bool $dry_run = false) {
        if (!$this->legacy_available()) {
            return array(
                'created' => 0,
                'updated' => 0,
                'activities_created' => 0,
                'activities_updated' => 0,
                'skipped' => 0,
                'dry_run' => $dry_run,
                'errors' => array(__('CPTs do plugin legado nao estao disponiveis.', 'api-no-parque')),
            );
        }

        $items = $this->payload_to_items($payload);
        $stats = array(
            'total_input' => count($items),
            'created' => 0,
            'updated' => 0,
            'activities_created' => 0,
            'activities_updated' => 0,
            'skipped' => 0,
            'dry_run' => $dry_run,
            'errors' => array(),
        );

        foreach ($items as $index => $item) {
            if (empty($item['name'])) {
                $stats['skipped']++;
                $stats['errors'][] = sprintf('Item %d sem nome.', $index + 1);
                continue;
            }

            $existing_id = $this->find_existing_uc($item);
            if ($dry_run) {
                $stats[$existing_id ? 'updated' : 'created']++;
                $stats['activities_created'] += count($item['atividades']);
                continue;
            }

            $before_id = $existing_id;
            $result = $this->import_item($item, false);
            if (is_wp_error($result)) {
                $stats['skipped']++;
                $stats['errors'][] = $result->get_error_message();
                continue;
            }

            $stats[$before_id ? 'updated' : 'created']++;
            $activity_stats = get_post_meta($result, '_api_np_last_activity_import_stats', true);
            if (is_array($activity_stats)) {
                $stats['activities_created'] += (int) $activity_stats['created'];
                $stats['activities_updated'] += (int) $activity_stats['updated'];
            }
            delete_post_meta($result, '_api_np_last_activity_import_stats');
        }

        return $stats;
    }

    private function import_item(array $item, bool $dry_run) {
        if ($dry_run) {
            return 0;
        }

        if (!$this->legacy_available()) {
            return new WP_Error('api_np_legacy_missing', __('Plugin legado indisponivel.', 'api-no-parque'));
        }

        if (empty($item['name'])) {
            return new WP_Error('api_np_invalid_uc', __('Nome da UC obrigatorio.', 'api-no-parque'));
        }

        $post_id = !empty($item['wp_id']) ? absint($item['wp_id']) : $this->find_existing_uc($item);
        $post_data = array(
            'post_type' => 'uc',
            'post_title' => sanitize_text_field($item['name']),
            'post_status' => 'publish',
            'post_excerpt' => sanitize_textarea_field($item['description']),
        );

        if ($post_id > 0) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = (int) $result;
        $this->update_uc_meta($post_id, $item);
        $this->update_uc_taxonomies($post_id, $item);
        $activity_stats = $this->update_uc_activities($post_id, $item);
        update_post_meta($post_id, '_api_np_last_activity_import_stats', $activity_stats);

        return $post_id;
    }

    private function update_uc_meta(int $post_id, array $item) {
        $meta_map = array(
            '_uc_breve_descricao' => 'description',
            '_uc_responsavel_atividade' => 'responsavel',
            '_uc_email' => 'email',
            '_uc_whatsapp' => 'whatsapp',
            '_uc_realizador_atividade' => 'realizador',
            '_uc_cep' => 'cep',
            '_uc_endereco' => 'endereco',
            '_uc_numero' => 'numero',
            '_uc_link_endereco' => 'link_do_endereco',
            '_uc_social' => 'social',
        );

        foreach ($meta_map as $meta_key => $item_key) {
            if (!empty($item['partial_update']) && !$this->item_field_provided($item, $item_key)) {
                continue;
            }

            $value = isset($item[$item_key]) ? $item[$item_key] : '';
            if ('' !== $value && null !== $value) {
                update_post_meta($post_id, $meta_key, sanitize_text_field((string) $value));
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }

        if (!empty($item['source_id'])) {
            update_post_meta($post_id, self::META_SOURCE_ID, sanitize_text_field((string) $item['source_id']));
        }

        if (isset($item['lat']) && is_numeric($item['lat'])) {
            update_post_meta($post_id, self::META_LAT, (string) (float) $item['lat']);
        }

        if (isset($item['lng']) && is_numeric($item['lng'])) {
            update_post_meta($post_id, self::META_LNG, (string) (float) $item['lng']);
        }

        if (empty($item['partial_update']) || $this->item_field_provided($item, 'location_meta')) {
            $location = isset($item['location_meta']) && is_array($item['location_meta']) ? $item['location_meta'] : array();
            update_post_meta($post_id, self::META_LOCATION_SOURCE, sanitize_text_field((string) ($location['source'] ?? '')));
            update_post_meta($post_id, self::META_LOCATION_PRECISION, sanitize_text_field((string) ($location['precision'] ?? '')));
            update_post_meta($post_id, self::META_LOCATION_QUERY, sanitize_text_field((string) ($location['query'] ?? '')));
            update_post_meta($post_id, self::META_LOCATION_DISPLAY_NAME, sanitize_text_field((string) ($location['display_name'] ?? '')));
        }

        update_post_meta($post_id, self::META_IMPORTED_AT, current_time('mysql'));
    }

    private function update_uc_taxonomies(int $post_id, array $item) {
        if (!empty($item['biome'])) {
            $this->assign_terms($post_id, 'bioma', $item['biome']);
        }

        if (!empty($item['city'])) {
            $uf_id = 0;
            if (!empty($item['state'])) {
                $uf_id = $this->find_or_create_uf($item['state']);
            }
            $city_term_id = $this->find_or_create_city($item['city'], $uf_id);
            if ($city_term_id > 0) {
                wp_set_object_terms($post_id, array($city_term_id), 'cidade', false);
            }
        }
    }

    private function update_uc_activities(int $uc_id, array $item) {
        $stats = array('created' => 0, 'updated' => 0);

        if (empty($item['activities_provided'])) {
            return $stats;
        }

        $existing_activity_ids = get_post_meta($uc_id, '_uc_atividade_ids', true);
        $activity_ids = is_array($existing_activity_ids) ? array_map('intval', $existing_activity_ids) : array();

        foreach ($item['atividades'] as $index => $activity) {
            $title = sanitize_text_field((string) ($activity['titulo'] ?? $activity['title'] ?? $item['activity'] ?? ''));
            if (!$title) {
                continue;
            }

            $source_key = $this->activity_source_key($item, $index);
            $activity_id = $this->find_existing_activity($source_key);
            $was_existing = $activity_id > 0;

            $post_data = array(
                'post_type' => 'atividade',
                'post_title' => $title,
                'post_status' => 'publish',
                'post_excerpt' => sanitize_textarea_field((string) ($activity['descricao'] ?? '')),
            );

            if ($activity_id > 0) {
                $post_data['ID'] = $activity_id;
                $result = wp_update_post($post_data, true);
            } else {
                $result = wp_insert_post($post_data, true);
            }

            if (is_wp_error($result)) {
                continue;
            }

            $activity_id = (int) $result;
            update_post_meta($activity_id, self::META_ACTIVITY_SOURCE_KEY, $source_key);
            update_post_meta($activity_id, '_atividade_ativo', '1');
            update_post_meta($activity_id, '_atividade_descricao', sanitize_textarea_field((string) ($activity['descricao'] ?? '')));
            update_post_meta($activity_id, '_atividade_data', sanitize_text_field((string) ($activity['data'] ?? '')));
            update_post_meta($activity_id, '_atividade_horario', sanitize_text_field((string) ($activity['horario'] ?? '')));
            update_post_meta($activity_id, self::META_ACTIVITY_UC_ID, (string) $uc_id);

            if (!empty($activity['dificuldade'])) {
                $this->assign_terms($activity_id, 'dificuldade', $activity['dificuldade']);
            }
            if (!empty($activity['publico'])) {
                $this->assign_terms($activity_id, 'publico', $activity['publico']);
            }
            if (!empty($activity['tipo'])) {
                $this->assign_terms($activity_id, 'tipo_atividade', $activity['tipo']);
            }

            $activity_ids[] = $activity_id;
            $stats[$was_existing ? 'updated' : 'created']++;
        }

        update_post_meta($uc_id, '_uc_atividade_ids', array_values(array_unique(array_map('intval', $activity_ids))));

        return $stats;
    }

    private function payload_to_items(array $payload) {
        $items = array();

        if (isset($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                $items[] = $this->normalise_input_item($item);
            }
            return $items;
        }

        if (isset($payload['records']) && is_array($payload['records'])) {
            foreach ($payload['records'] as $record) {
                $items[] = $this->record_to_item($record);
            }
            return $items;
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            foreach ($payload as $item) {
                $items[] = $this->normalise_input_item($item);
            }
        }

        return $items;
    }

    private function record_to_item(array $record) {
        $normalized = isset($record['normalized']) && is_array($record['normalized']) ? $record['normalized'] : $record;
        $location = isset($record['location']) && is_array($record['location']) ? $record['location'] : array();
        $activities = isset($normalized['atividades_lista']) && is_array($normalized['atividades_lista']) ? $normalized['atividades_lista'] : array();

        return $this->normalise_input_item(array(
            'source_id' => $record['id'] ?? '',
            'name' => $normalized['nome'] ?? '',
            'description' => $normalized['descricao'] ?? '',
            'responsavel' => $normalized['responsavel_atividade'] ?? '',
            'email' => $normalized['email'] ?? '',
            'whatsapp' => $normalized['whatsapp'] ?? '',
            'realizador' => $normalized['realizador_atividade'] ?? '',
            'biome' => $normalized['bioma'] ?? '',
            'state' => $normalized['estado'] ?? '',
            'city' => $normalized['municipio'] ?? '',
            'social' => $normalized['social'] ?? '',
            'cep' => $normalized['cep'] ?? '',
            'endereco' => $normalized['endereco'] ?? '',
            'numero' => $normalized['numero'] ?? '',
            'link_do_endereco' => $normalized['link_do_endereco'] ?? '',
            'lat' => $location['lat'] ?? null,
            'lng' => $location['lng'] ?? null,
            'atividades' => $activities,
            'location_meta' => $location,
        ));
    }

    private function normalise_input_item($item) {
        if (!is_array($item)) {
            $item = array();
        }

        $provided_keys = $this->normalised_provided_keys($item);
        $activities = array();
        $activities_provided = false;
        if (!empty($item['atividades']) && is_array($item['atividades'])) {
            $activities = $item['atividades'];
            $activities_provided = true;
        } elseif (!empty($item['activities']) && is_array($item['activities'])) {
            $activities = $item['activities'];
            $activities_provided = true;
        } elseif (!empty($item['activity'])) {
            $activities = array(array(
                'titulo' => $item['activity'],
                'descricao' => $item['description'] ?? '',
            ));
            $activities_provided = true;
        } elseif (array_key_exists('atividades', $item) || array_key_exists('activities', $item)) {
            $activities_provided = true;
        }

        return array(
            'wp_id' => isset($item['wp_id']) ? absint($item['wp_id']) : 0,
            'source_id' => $item['source_id'] ?? $item['id'] ?? '',
            'name' => $item['name'] ?? $item['nome'] ?? '',
            'state' => $item['state'] ?? $item['estado'] ?? '',
            'city' => $item['city'] ?? $item['municipio'] ?? $item['cidade'] ?? '',
            'biome' => $item['biome'] ?? $item['bioma'] ?? '',
            'activity' => $item['activity'] ?? '',
            'lat' => $item['lat'] ?? null,
            'lng' => $item['lng'] ?? null,
            'description' => $item['description'] ?? $item['descricao'] ?? '',
            'responsavel' => $item['responsavel'] ?? $item['responsavel_atividade'] ?? '',
            'email' => $item['email'] ?? '',
            'whatsapp' => $item['whatsapp'] ?? '',
            'realizador' => $item['realizador'] ?? $item['realizador_atividade'] ?? '',
            'social' => $item['social'] ?? '',
            'cep' => $item['cep'] ?? '',
            'endereco' => $item['endereco'] ?? '',
            'numero' => $item['numero'] ?? '',
            'link_do_endereco' => $item['link_do_endereco'] ?? '',
            'atividades' => array_values(array_filter($activities, 'is_array')),
            'activities_provided' => $activities_provided,
            'location_meta' => isset($item['location_meta']) && is_array($item['location_meta']) ? $item['location_meta'] : array(),
            '_provided_keys' => $provided_keys,
        );
    }

    private function normalised_provided_keys(array $item) {
        $aliases = array(
            'id' => 'source_id',
            'nome' => 'name',
            'estado' => 'state',
            'municipio' => 'city',
            'cidade' => 'city',
            'bioma' => 'biome',
            'descricao' => 'description',
            'responsavel_atividade' => 'responsavel',
            'realizador_atividade' => 'realizador',
            'atividades' => 'atividades',
            'activities' => 'atividades',
        );

        $provided = array();
        foreach (array_keys($item) as $key) {
            $provided[] = $key;
            if (isset($aliases[$key])) {
                $provided[] = $aliases[$key];
            }
        }

        return array_values(array_unique($provided));
    }

    private function item_field_provided(array $item, string $field) {
        $provided = isset($item['_provided_keys']) && is_array($item['_provided_keys']) ? $item['_provided_keys'] : array();
        return in_array($field, $provided, true);
    }

    private function format_uc_item(int $post_id) {
        if (!$this->is_uc($post_id)) {
            return null;
        }

        $city_data = $this->get_uc_city_state($post_id);
        $biomes = wp_get_post_terms($post_id, 'bioma', array('fields' => 'names'));
        $biome = !is_wp_error($biomes) && !empty($biomes) ? implode(' / ', $biomes) : '';
        $activities = $this->get_uc_activities($post_id);
        $first_activity = isset($activities[0]) ? $activities[0] : array();
        $lat = get_post_meta($post_id, self::META_LAT, true);
        $lng = get_post_meta($post_id, self::META_LNG, true);
        $fallback_coords = $this->extract_coordinates_from_uc($post_id);
        if (!is_numeric($lat) && is_array($fallback_coords)) {
            $lat = $fallback_coords['lat'];
        }
        if (!is_numeric($lng) && is_array($fallback_coords)) {
            $lng = $fallback_coords['lng'];
        }
        $image = $this->get_uc_image($post_id);
        $carry_items = $this->get_uc_carry_items($post_id);

        return array(
            'id' => $post_id,
            'source_id' => get_post_meta($post_id, self::META_SOURCE_ID, true),
            'slug' => get_post_field('post_name', $post_id),
            'url' => get_permalink($post_id),
            'name' => get_the_title($post_id),
            'title' => get_the_title($post_id),
            'content' => apply_filters('the_content', get_post_field('post_content', $post_id)),
            'excerpt' => get_the_excerpt($post_id),
            'state' => $city_data['state'],
            'uf' => $city_data['uf'],
            'city' => $city_data['city'],
            'biome' => $biome,
            'activity' => $first_activity['titulo'] ?? '',
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lng' => is_numeric($lng) ? (float) $lng : null,
            'description' => get_post_meta($post_id, '_uc_breve_descricao', true) ?: get_the_excerpt($post_id),
            'image' => $image,
            'image_url' => $image['url'],
            'thumbnail' => $image['sizes']['thumbnail'] ?? $image['url'],
            'tags' => array_values(array_filter(array($biome, $city_data['state']))),
            'responsavel' => get_post_meta($post_id, '_uc_responsavel_atividade', true),
            'email' => get_post_meta($post_id, '_uc_email', true),
            'whatsapp' => get_post_meta($post_id, '_uc_whatsapp', true),
            'realizador' => get_post_meta($post_id, '_uc_realizador_atividade', true),
            'social' => get_post_meta($post_id, '_uc_social', true),
            'cep' => get_post_meta($post_id, '_uc_cep', true),
            'endereco' => get_post_meta($post_id, '_uc_endereco', true),
            'numero' => get_post_meta($post_id, '_uc_numero', true),
            'link_do_endereco' => get_post_meta($post_id, '_uc_link_endereco', true),
            'oque_levar' => $carry_items,
            'atividades' => $activities,
            'location_meta' => array(
                'source' => get_post_meta($post_id, self::META_LOCATION_SOURCE, true),
                'precision' => get_post_meta($post_id, self::META_LOCATION_PRECISION, true),
                'query' => get_post_meta($post_id, self::META_LOCATION_QUERY, true),
                'display_name' => get_post_meta($post_id, self::META_LOCATION_DISPLAY_NAME, true),
            ),
            'meta' => array(
                '_uc_breve_descricao' => get_post_meta($post_id, '_uc_breve_descricao', true),
                '_uc_responsavel_atividade' => get_post_meta($post_id, '_uc_responsavel_atividade', true),
                '_uc_email' => get_post_meta($post_id, '_uc_email', true),
                '_uc_whatsapp' => get_post_meta($post_id, '_uc_whatsapp', true),
                '_uc_realizador_atividade' => get_post_meta($post_id, '_uc_realizador_atividade', true),
                '_uc_cep' => get_post_meta($post_id, '_uc_cep', true),
                '_uc_endereco' => get_post_meta($post_id, '_uc_endereco', true),
                '_uc_numero' => get_post_meta($post_id, '_uc_numero', true),
                '_uc_link_endereco' => get_post_meta($post_id, '_uc_link_endereco', true),
                '_uc_imagem' => get_post_meta($post_id, '_uc_imagem', true),
                '_uc_social' => get_post_meta($post_id, '_uc_social', true),
                self::META_UC_CARRY_ITEM_IDS => get_post_meta($post_id, self::META_UC_CARRY_ITEM_IDS, true),
            ),
        );
    }

    private function get_uc_carry_items(int $post_id) {
        $ids = get_post_meta($post_id, self::META_UC_CARRY_ITEM_IDS, true);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : array();

        if (empty($ids)) {
            return array();
        }

        $items = array();
        foreach ($this->get_carry_item_posts($ids) as $item) {
            $icon = $this->normalize_fa_icon_class((string) get_post_meta($item->ID, self::META_CARRY_ITEM_ICON, true));
            $items[] = array(
                'id' => (int) $item->ID,
                'slug' => get_post_field('post_name', $item->ID),
                'name' => get_the_title($item),
                'title' => get_the_title($item),
                'icon' => $icon,
                'icon_class' => 'fa-solid ' . $icon,
            );
        }

        return $items;
    }

    private function get_uc_activities(int $uc_id) {
        $ids = $this->get_uc_activity_ids($uc_id);
        if (empty($ids)) {
            return array();
        }

        $posts = get_posts(array(
            'post_type' => 'atividade',
            'post_status' => 'publish',
            'post__in' => array_map('intval', $ids),
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ));

        $items = array();
        foreach ($posts as $post) {
            $image = $this->get_attachment_image_data(get_post_thumbnail_id($post->ID));
            $activity_types = $this->get_activity_type_data($post->ID);
            $items[] = array(
                'id' => $post->ID,
                'slug' => get_post_field('post_name', $post->ID),
                'url' => get_permalink($post->ID),
                'titulo' => get_the_title($post->ID),
                'title' => get_the_title($post->ID),
                'data' => get_post_meta($post->ID, '_atividade_data', true),
                'horario' => get_post_meta($post->ID, '_atividade_horario', true),
                'descricao' => get_post_meta($post->ID, '_atividade_descricao', true) ?: get_the_excerpt($post->ID),
                'description' => get_post_meta($post->ID, '_atividade_descricao', true) ?: get_the_excerpt($post->ID),
                'content' => apply_filters('the_content', get_post_field('post_content', $post->ID)),
                'excerpt' => get_the_excerpt($post->ID),
                'publico' => $this->terms_string($post->ID, 'publico'),
                'dificuldade' => $this->terms_string($post->ID, 'dificuldade'),
                'tipo' => $this->terms_string($post->ID, 'tipo_atividade'),
                'tipos' => $activity_types,
                'tipo_icons' => wp_list_pluck($activity_types, 'icon'),
                'uc_id' => (int) get_post_meta($post->ID, self::META_ACTIVITY_UC_ID, true),
                'image' => $image,
                'image_url' => $image['url'],
                'thumbnail' => $image['sizes']['thumbnail'] ?? $image['url'],
                'meta' => array(
                    '_atividade_data' => get_post_meta($post->ID, '_atividade_data', true),
                    '_atividade_horario' => get_post_meta($post->ID, '_atividade_horario', true),
                    '_atividade_descricao' => get_post_meta($post->ID, '_atividade_descricao', true),
                    '_atividade_ativo' => get_post_meta($post->ID, '_atividade_ativo', true),
                    self::META_ACTIVITY_UC_ID => get_post_meta($post->ID, self::META_ACTIVITY_UC_ID, true),
                ),
            );
        }

        return $items;
    }

    private function get_uc_activity_ids(int $uc_id) {
        $ids = get_post_meta($uc_id, '_uc_atividade_ids', true);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : array();

        $activity_posts = get_posts(array(
            'post_type' => 'atividade',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => self::META_ACTIVITY_UC_ID,
            'meta_value' => (string) $uc_id,
        ));

        foreach ($activity_posts as $activity_id) {
            $ids[] = (int) $activity_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function get_activity_type_data(int $post_id) {
        $terms = wp_get_post_terms($post_id, 'tipo_atividade');

        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }

        $items = array();
        foreach ($terms as $term) {
            $items[] = array(
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'icon' => get_term_meta($term->term_id, self::META_ACTIVITY_TYPE_ICON, true) ?: '',
            );
        }

        return $items;
    }

    private function get_uc_image(int $post_id) {
        $image_id = (int) get_post_meta($post_id, '_uc_imagem', true);

        if ($image_id <= 0) {
            $image_id = (int) get_post_thumbnail_id($post_id);
        }

        return $this->get_attachment_image_data($image_id);
    }

    private function get_attachment_image_data(int $attachment_id) {
        if ($attachment_id <= 0) {
            return array(
                'id' => 0,
                'url' => '',
                'alt' => '',
                'caption' => '',
                'sizes' => array(),
            );
        }

        $sizes = array();
        foreach (array('thumbnail', 'medium', 'medium_large', 'large', 'full') as $size) {
            $url = wp_get_attachment_image_url($attachment_id, $size);
            if ($url) {
                $sizes[$size] = $url;
            }
        }

        return array(
            'id' => $attachment_id,
            'url' => $sizes['full'] ?? '',
            'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'caption' => wp_get_attachment_caption($attachment_id) ?: '',
            'sizes' => $sizes,
        );
    }

    private function get_uc_city_state(int $post_id) {
        $result = array('city' => '', 'state' => '', 'uf' => '');
        $terms = wp_get_object_terms($post_id, 'cidade', array('fields' => 'all'));

        if (!is_wp_error($terms) && !empty($terms)) {
            $term = $terms[0];
            $result['city'] = $term->name;
            $uf_id = (int) get_term_meta($term->term_id, '_cidade_uf', true);
        } else {
            $result['city'] = (string) get_post_meta($post_id, '_uc_municipio', true);
            $uf_ids = get_post_meta($post_id, '_uc_uf_ids', true);
            if (is_array($uf_ids) && !empty($uf_ids)) {
                $uf_id = (int) reset($uf_ids);
            } else {
                $uf_id = (int) get_post_meta($post_id, '_uc_uf_id', true);
            }
        }

        if (!empty($uf_id) && $uf_id > 0) {
            $result['state'] = get_the_title($uf_id);
            $result['uf'] = get_post_meta($uf_id, '_uf_sigla', true);
        }

        return $result;
    }

    private function extract_coordinates_from_uc(int $post_id) {
        $texts = array(
            get_post_meta($post_id, '_uc_link_endereco', true),
            get_post_meta($post_id, '_uc_endereco', true),
        );

        foreach ($texts as $text) {
            $coords = $this->extract_coordinates_from_text((string) $text);
            if (is_array($coords)) {
                return $coords;
            }
        }

        return null;
    }

    private function extract_coordinates_from_text(string $text) {
        if ('' === trim($text)) {
            return null;
        }

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $text, $matches)) {
            return array(
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            );
        }

        if (preg_match('/[?&]ll=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $text, $matches)) {
            return array(
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            );
        }

        if (preg_match('/[?&]q=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $text, $matches)) {
            return array(
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
            );
        }

        return null;
    }

    private function find_existing_uc(array $item) {
        if (!empty($item['wp_id']) && $this->is_uc((int) $item['wp_id'])) {
            return (int) $item['wp_id'];
        }

        if (!empty($item['source_id'])) {
            $posts = get_posts(array(
                'post_type' => 'uc',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => self::META_SOURCE_ID,
                'meta_value' => (string) $item['source_id'],
            ));

            if (!empty($posts)) {
                return (int) $posts[0];
            }
        }

        if (!empty($item['name'])) {
            $post = $this->get_post_by_title($item['name'], 'uc');
            if ($post > 0) {
                return $post;
            }
        }

        return 0;
    }

    private function find_existing_activity(string $source_key) {
        $posts = get_posts(array(
            'post_type' => 'atividade',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_ACTIVITY_SOURCE_KEY,
            'meta_value' => $source_key,
        ));

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    private function activity_source_key(array $item, int $index) {
        $source_id = !empty($item['source_id']) ? (string) $item['source_id'] : md5((string) $item['name']);
        return $source_id . ':' . $index;
    }

    private function find_or_create_uf(string $state_name) {
        $state_name = trim($state_name);
        if ('' === $state_name) {
            return 0;
        }

        $existing = $this->get_post_by_title($state_name, 'uf');
        if ($existing > 0) {
            return $existing;
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'uf',
            'post_title' => sanitize_text_field($state_name),
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($post_id)) {
            return 0;
        }

        update_post_meta((int) $post_id, '_uf_sigla', $this->state_to_uf($state_name));
        return (int) $post_id;
    }

    private function find_or_create_city(string $city_name, int $uf_id = 0) {
        $city_name = trim($city_name);
        if ('' === $city_name) {
            return 0;
        }

        $term = term_exists($city_name, 'cidade');
        if (!$term) {
            $term = wp_insert_term($city_name, 'cidade');
        }

        if (is_wp_error($term) || empty($term['term_id'])) {
            return 0;
        }

        $term_id = (int) $term['term_id'];
        if ($uf_id > 0) {
            update_term_meta($term_id, '_cidade_uf', $uf_id);
        }

        return $term_id;
    }

    private function assign_terms(int $post_id, string $taxonomy, $value) {
        $values = is_array($value) ? $value : preg_split('/\s*[,\/]\s*/', (string) $value);
        $term_ids = array();

        foreach ($values as $name) {
            $name = trim((string) $name);
            if ('' === $name) {
                continue;
            }

            if ('tipo_atividade' === $taxonomy) {
                $name = $this->match_activity_type_name($name);
                if ('' === $name) {
                    continue;
                }
            }

            $term = term_exists($name, $taxonomy);
            if (!$term) {
                $term = wp_insert_term($name, $taxonomy);
            }

            if (!is_wp_error($term) && !empty($term['term_id'])) {
                $term_ids[] = (int) $term['term_id'];
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
        }
    }

    private function match_activity_type_name(string $value) {
        $needle = $this->normalize_key($value);
        $aliases = array(
            'trilha' => array('trilha', 'caminhada'),
            'Cachoeira' => array('cachoeira', 'banho', 'rio'),
            'Mutirao de limpeza' => array('limpeza', 'mutirao', 'residuo', 'lixo'),
            'Plantio' => array('plantio', 'plantar', 'muda', 'arvore'),
            'Observacao de fauna' => array('fauna', 'mamifero', 'animal'),
            'Caverna' => array('caverna', 'gruta'),
            'Educacao ambiental' => array('educacao ambiental', 'sensibilizacao', 'palestra'),
            'Acampamento' => array('acampamento'),
            'Atividade noturna' => array('noturna', 'noite', 'lua'),
            'Ciclismo' => array('ciclismo', 'bike', 'bicicleta'),
            'Oficina' => array('oficina'),
            'Yoga' => array('yoga'),
            'Cinema' => array('cinema', 'filme', 'cine'),
            'Sarau' => array('sarau'),
            'Exposicao' => array('exposicao', 'exposiÃ§ao', 'mostra'),
            'Feira' => array('feira'),
            'Programacao Cultural' => array('programacao cultural', 'cultural'),
            'Teatro' => array('teatro'),
            'Piquenique' => array('piquenique', 'picnic'),
            'Observacao de Aves' => array('observacao de aves', 'passarinhada', 'aves'),
            'Rapel' => array('rapel'),
            'Banho de Floresta' => array('banho de floresta'),
            'Meditacao' => array('meditacao', 'meditar'),
            'Canoagem' => array('canoagem', 'caiaque', 'barco'),
            'Tirolesa' => array('tirolesa'),
            'Contacao de Historias' => array('contacao', 'historias', 'contar historias'),
        );

        foreach ($this->get_activity_types_catalog() as $item) {
            if ($needle === $this->normalize_key($item['name'])) {
                return $item['name'];
            }
        }

        foreach ($aliases as $canonical => $words) {
            foreach ($words as $word) {
                if (false !== strpos($needle, $this->normalize_key($word))) {
                    return 'trilha' === $canonical ? 'Trilha' : $canonical;
                }
            }
        }

        return '';
    }

    private function normalize_key(string $value) {
        return strtolower(remove_accents(trim($value)));
    }

    private function decode_label(string $value) {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    private function normalize_fa_icon_class(string $value) {
        $parts = preg_split('/\s+/', trim($value));

        foreach ((array) $parts as $part) {
            $part = sanitize_html_class($part);
            if (0 === strpos($part, 'fa-') && !in_array($part, array('fa-solid', 'fa-regular', 'fa-brands'), true)) {
                return $part;
            }
        }

        return 'fa-circle';
    }

    private function terms_string(int $post_id, string $taxonomy) {
        $terms = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'names'));
        return !is_wp_error($terms) && !empty($terms) ? implode(', ', $terms) : '';
    }

    private function get_post_by_title(string $title, string $post_type) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'title' => sanitize_text_field($title),
            'fields' => 'ids',
        ));

        return !empty($posts) ? (int) $posts[0] : 0;
    }

    private function state_to_uf(string $state_name) {
        $key = remove_accents(mb_strtolower(trim($state_name), 'UTF-8'));
        return $this->uf_by_state[$key] ?? strtoupper(substr($state_name, 0, 2));
    }

    private function legacy_available() {
        return post_type_exists('uc') && post_type_exists('atividade') && post_type_exists('uf') && taxonomy_exists('bioma') && taxonomy_exists('cidade');
    }

    private function asset_version(string $relative_path) {
        $path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');
        return file_exists($path) ? (string) filemtime($path) : self::VERSION;
    }

    private function is_uc(int $post_id) {
        return $post_id > 0 && 'uc' === get_post_type($post_id);
    }

    private function count_posts(string $post_type) {
        if (!post_type_exists($post_type)) {
            return 0;
        }

        $counts = wp_count_posts($post_type);
        return isset($counts->publish) ? (int) $counts->publish : 0;
    }
}

Api_No_Parque::instance();
