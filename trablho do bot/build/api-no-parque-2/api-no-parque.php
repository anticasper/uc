<?php
/**
 * Plugin Name: API No Parque
 * Plugin URI: https://barradois.com
 * Description: API e importador para integrar a base JSON com os cadastros do plugin Um Dia No Parque.
 * Version: 0.1.1
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
    const VERSION = '0.1.1';
    const REST_NAMESPACE = 'api-no-parque/v1';

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

            if (!empty($activity['dificuldade'])) {
                $this->assign_terms($activity_id, 'dificuldade', $activity['dificuldade']);
            }
            if (!empty($activity['publico'])) {
                $this->assign_terms($activity_id, 'publico', $activity['publico']);
            }
            if (!empty($activity['tipo'])) {
                $this->assign_terms($activity_id, 'tipo_atividade', $activity['tipo']);
            } elseif (!empty($title)) {
                $this->assign_terms($activity_id, 'tipo_atividade', $title);
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
            ),
        );
    }

    private function get_uc_activities(int $uc_id) {
        $ids = get_post_meta($uc_id, '_uc_atividade_ids', true);
        if (!is_array($ids) || empty($ids)) {
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
                'image' => $image,
                'image_url' => $image['url'],
                'thumbnail' => $image['sizes']['thumbnail'] ?? $image['url'],
                'meta' => array(
                    '_atividade_data' => get_post_meta($post->ID, '_atividade_data', true),
                    '_atividade_horario' => get_post_meta($post->ID, '_atividade_horario', true),
                    '_atividade_descricao' => get_post_meta($post->ID, '_atividade_descricao', true),
                    '_atividade_ativo' => get_post_meta($post->ID, '_atividade_ativo', true),
                ),
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
