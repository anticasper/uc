<?php
/**
 * AJAX Handler Class
 *
 * Handles AJAX requests for Um Dia No Parque plugin.
 * Only includes active, non-placeholder endpoints.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class Um_Dia_No_Parque_AJAX {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return self
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
     * Constructor — registers all AJAX handlers.
     */
    private function __construct() {
        // Elementor: busca de ucs.
        add_action('wp_ajax_umdnp_buscar_parques', array($this, 'handle_elementor_search'));
        add_action('wp_ajax_nopriv_umdnp_buscar_parques', array($this, 'handle_elementor_search'));

        // Elementor: formulário de contato via email.
        add_action('wp_ajax_umdnp_enviar_formulario', array($this, 'handle_elementor_form'));
        add_action('wp_ajax_nopriv_umdnp_enviar_formulario', array($this, 'handle_elementor_form'));

        // Mapa interativo: dados dos ucs.
        add_action('wp_ajax_umdnp_get_parques_mapa', array($this, 'handle_map_data'));
        add_action('wp_ajax_nopriv_umdnp_get_parques_mapa', array($this, 'handle_map_data'));

        // Geocode UCs not yet in cache.
        add_action('wp_ajax_umdnp_geocode_ucs', array($this, 'handle_geocode_ucs'));

        // Cidades por UF (dependent dropdown).
        add_action('wp_ajax_umdnp_get_cidades_por_uf', array($this, 'handle_get_cidades_por_uf'));
    }

    // ---------------------------------------------------------------
    // Elementor: Busca de UCs
    // ---------------------------------------------------------------

    /**
     * Handle Elementor search/busca de ucs via AJAX.
     *
     * @since 1.0.0
     */
    public function handle_elementor_search() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'um_dia_no_parque_elementor_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
        }

        $search     = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $bioma      = isset($_POST['categoria']) ? sanitize_text_field(wp_unslash($_POST['categoria'])) : '';
        $municipio  = isset($_POST['municipio']) ? sanitize_text_field(wp_unslash($_POST['municipio'])) : '';

        $query_args = array(
            'post_type'      => 'uc',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        if (!empty($bioma)) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'bioma',
                'field'    => 'slug',
                'terms'    => $bioma,
            );
        }

        if (!empty($municipio)) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'cidade',
                'field'    => 'name',
                'terms'    => $municipio,
            );
        }

        $query = new WP_Query($query_args);

        ob_start();

        if ($query->have_posts()) {
            echo '<div class="ucs-search-results">';
            while ($query->have_posts()) {
                $query->the_post();
                $uc_id = get_the_ID();
                $city_terms = wp_get_object_terms($uc_id, 'cidade', array('fields' => 'names'));
                $municipio  = !empty($city_terms) ? $city_terms[0] : '';
                ?>
                <div class="uc-search-item">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="uc-search-image">
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('thumbnail'); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="uc-search-info">
                        <h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                        <?php if ($municipio) : ?>
                            <span class="uc-search-cidade"><?php echo esc_html($municipio); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo '<p class="no-ucs">' . esc_html__('Nenhum uc encontrado.', 'um-dia-no-parque') . '</p>';
        }

        wp_reset_postdata();

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    // ---------------------------------------------------------------
    // Elementor: Formulário de Contato
    // ---------------------------------------------------------------

    /**
     * Handle Elementor form submission via AJAX.
     * Sends email notification to the site admin.
     *
     * @since 1.0.0
     */
    public function handle_elementor_form() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'um_dia_no_parque_elementor_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
        }

        $form_type = isset($_POST['form_type']) ? sanitize_text_field(wp_unslash($_POST['form_type'])) : 'contato';
        $name      = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email     = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone     = isset($_POST['telefone']) ? sanitize_text_field(wp_unslash($_POST['telefone'])) : '';
        $message   = isset($_POST['mensagem']) ? sanitize_textarea_field(wp_unslash($_POST['mensagem'])) : '';

        if (empty($name)) {
            wp_send_json_error(array('message' => __('O campo nome é obrigatório.', 'um-dia-no-parque')));
        }

        $to      = get_option('admin_email');
        $subject = sprintf(
            /* translators: %s: Form type */
            __('[Um Dia No UC] Nova submissão: %s', 'um-dia-no-parque'),
            $form_type
        );

        $body = array(
            __('Nome:', 'um-dia-no-parque') . ' ' . $name,
            __('E-mail:', 'um-dia-no-parque') . ' ' . $email,
            __('Telefone:', 'um-dia-no-parque') . ' ' . $phone,
            __('Mensagem:', 'um-dia-no-parque') . ' ' . $message,
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        wp_mail($to, $subject, implode("\n", $body), $headers);

        wp_send_json_success(array(
            'message' => __('Formulário enviado com sucesso! Entraremos em contato em breve.', 'um-dia-no-parque'),
        ));
    }

    // ---------------------------------------------------------------
    // Mapa Interativo
    // ---------------------------------------------------------------

    /**
     * Handle AJAX request for interactive map data.
     *
     * Returns JSON with UC markers filtered by search, tipo de atividade,
     * cidade, parque, and bioma. Geocodes UC addresses via Nominatim (OpenStreetMap)
     * with transient caching — no new CPT fields required.
     *
     * @since 1.0.0
     */
    public function handle_map_data() {
        // Public GET endpoint — nonce optional (data is public).
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !empty($_POST)) {
            $nonce = isset($_GET['nonce']) ? sanitize_key($_GET['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'um_dia_no_parque_elementor_nonce')) {
                wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
                return;
            }
        }

        $search         = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $parque         = isset($_GET['parque']) ? absint($_GET['parque']) : 0;
        $unidade        = isset($_GET['unidade']) ? sanitize_text_field(wp_unslash($_GET['unidade'])) : '';
        $cidade         = isset($_GET['cidade']) ? sanitize_text_field(wp_unslash($_GET['cidade'])) : '';
        $tipo_atividade = isset($_GET['tipo_atividade']) ? sanitize_text_field(wp_unslash($_GET['tipo_atividade'])) : '';

        $query_args = array(
            'post_type'      => 'uc',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );

        // --- Search: unified across title, CEP, and city ---
        if (!empty($search)) {
            $query_args['s'] = $search;

            // Collect post IDs that match via CEP meta or cidade taxonomy.
            $search_post_ids = array();

            // 1. CEP meta search.
            $cep_query = new WP_Query(array(
                'post_type'      => 'uc',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'post_status'    => 'publish',
                'meta_query'     => array(
                    array(
                        'key'     => Um_Dia_No_Parque_Meta::UC_CEP,
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ),
                ),
            ));
            if (!empty($cep_query->posts)) {
                $search_post_ids = array_merge($search_post_ids, $cep_query->posts);
            }

            // 2. Cidade taxonomy search (canonical source).
            $matching_terms = get_terms(array(
                'taxonomy'   => 'cidade',
                'name__like' => $search,
                'fields'     => 'ids',
                'hide_empty' => true,
            ));
            if (!empty($matching_terms) && !is_wp_error($matching_terms)) {
                $term_post_ids = get_objects_in_term($matching_terms, 'cidade');
                if (!empty($term_post_ids)) {
                    $search_post_ids = array_merge($search_post_ids, $term_post_ids);
                }
            }

            // If we found additional matches, constrain the query.
            if (!empty($search_post_ids)) {
                $search_post_ids = array_unique(array_map('intval', $search_post_ids));
                // If 's' is already set, merge with post__in so both title/content
                // AND taxonomy/meta matches are included.
                $query_args['post__in'] = $search_post_ids;
            }
        }

        // --- Filter by parque (specific UC ID) ---
        if ($parque > 0) {
            $query_args['p'] = $parque;
        }

        // --- Filter by unidade (bioma taxonomy) ---
        if (!empty($unidade)) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'bioma',
                'field'    => 'slug',
                'terms'    => $unidade,
            );
        }

        // --- Filter by cidade ---
        if (!empty($cidade)) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'cidade',
                'field'    => 'name',
                'terms'    => $cidade,
            );
        }

        // --- Filter by tipo de atividade (taxonomy) ---

        // --- Filter by tipo_atividade ---
        if (!empty($tipo_atividade)) {
            $atividades = get_posts(array(
                'post_type'      => 'atividade',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'tax_query'      => array(
                    array(
                        'taxonomy' => 'tipo_atividade',
                        'field'    => 'slug',
                        'terms'    => $tipo_atividade,
                    ),
                ),
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ));

            if (!empty($atividades)) {
                $atv_meta = array('relation' => 'OR');
                foreach ($atividades as $atv_id) {
                    $atv_meta[] = array(
                        'key'     => Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS,
                        'value'   => sprintf(':"%d";', $atv_id),
                        'compare' => 'LIKE',
                    );
                }
                $query_args['meta_query'][] = $atv_meta;
            } else {
                $query_args['post__in'] = array(0);
            }
        }

        $query   = new WP_Query($query_args);
        $markers = array();
        $geo_failures = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Read cidade from taxonomy (canonical).
                $city_terms = wp_get_object_terms($post_id, 'cidade', array('fields' => 'all'));
                $municipio  = !empty($city_terms) ? $city_terms[0]->name : '';
                $endereco  = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_ENDERECO, true);
                $numero    = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_NUMERO, true);
                $cep       = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_CEP, true);
                $thumbnail = get_the_post_thumbnail_url($post_id, 'thumbnail');

                // Resolve UF sigla from cidade term's _cidade_uf term meta.
                $uf_sigla = '';
                if (!empty($city_terms)) {
                    $uf_id = (int) get_term_meta($city_terms[0]->term_id, '_cidade_uf', true);
                    if ($uf_id > 0) {
                        $uf_post = get_post($uf_id);
                        if ($uf_post) {
                            $uf_sigla = get_post_meta($uf_post->ID, '_uf_sigla', true);
                        }
                    }
                }

                // Geocode address using Nominatim (cached in transient).
                $coords = $this->geocode_address($endereco, $numero, $municipio, $uf_sigla, $cep);
                if (null === $coords) {
                    $coords = array(
                        'lat' => -14.2350,
                        'lng' => -51.9253,
                    );
                }

                $biomas = wp_get_post_terms($post_id, 'bioma', array('fields' => 'names'));
                $bioma_names = !empty($biomas) && !is_wp_error($biomas) ? implode(', ', $biomas) : '';

                $markers[] = array(
                    'id'        => $post_id,
                    'name'      => get_the_title(),
                    'permalink' => get_permalink(),
                    'lat'       => $coords['lat'],
                    'lng'       => $coords['lng'],
                    'cidade'    => $municipio ?: '',
                    'uf'        => $uf_sigla,
                    'endereco'  => $endereco ?: '',
                    'numero'    => $numero ?: '',
                    'cep'       => $cep ?: '',
                    'biomas'    => $bioma_names,
                    'thumbnail' => $thumbnail ?: '',
                    'excerpt'   => get_the_excerpt(),
                );
            }
            wp_reset_postdata();
        }

        Um_Dia_No_Parque_Logger::info('Mapa: dados carregados', array(
            'filters' => array_filter(array(
                'search'         => $search,
                'parque'         => $parque,
                'unidade'        => $unidade,
                'cidade'         => $cidade,
                'tipo_atividade' => $tipo_atividade,
            )),
            'total'        => count($markers),
            'geo_failures' => $geo_failures,
        ));

        wp_send_json_success(array(
            'markers' => $markers,
            'total'   => count($markers),
        ));
    }

    /**
     * Geocode a UC address via Nominatim (OpenStreetMap).
     *
     * Results are cached in a transient (7 days for success, 1 hour for
     * failures/empty results). When no cache is found AND $allow_http is
     * true, makes a live HTTP call to Nominatim (with 1s rate limiting).
     *
     * Address is built from: endereco + numero, CEP, municipio, UF, Brasil.
     *
     * @since  1.0.0
     * @param  string $endereco  Street address.
     * @param  string $numero    Number.
     * @param  string $municipio City/municipality.
     * @param  string $uf_sigla  UF abbreviation (SP, RJ, …).
     * @param  string $cep       CEP / postal code (optional).
     * @param  bool   $allow_http If true, makes HTTP call when cache misses.
     * @return array|null        { 'lat': float, 'lng': float } or null.
     */
    public function geocode_address($endereco, $numero, $municipio, $uf_sigla, $cep = '', $allow_http = false) {
        $parts = array_values(array_filter(array(
            $this->build_endereco_full($endereco, $numero),
            $cep,
            $municipio,
            $uf_sigla,
            'Brasil',
        )));

        if (count($parts) < 2) {
            return null;
        }

        $address   = implode(', ', $parts);
        $cache_key = 'umdnp_geo_' . md5($address);
        $cached    = get_transient($cache_key);

        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        // Negative cache: if we have a failure marker, skip.
        if ('__failed__' === $cached) {
            return null;
        }

        // Without HTTP permission, return null (caller uses defaults).
        if (!$allow_http) {
            return null;
        }

        // --- Make live Nominatim HTTP request ---
        $url = add_query_arg(array(
            'q'      => $address,
            'format' => 'json',
            'limit'  => 1,
        ), 'https://nominatim.openstreetmap.org/search');

        $response = wp_remote_get($url, array(
            'timeout'    => 10,
            'user-agent' => 'UmDiaNoParque/1.0 (plugin wordpress)',
            'headers'    => array(
                'Accept-Language' => 'pt-BR,pt;q=0.9',
            ),
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            // Cache failure for 1 hour to avoid repeated retries.
            set_transient($cache_key, '__failed__', HOUR_IN_SECONDS);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
            set_transient($cache_key, '__failed__', HOUR_IN_SECONDS);
            return null;
        }

        $coords = array(
            'lat' => (float) $data[0]['lat'],
            'lng' => (float) $data[0]['lon'],
        );

        // Success cache: 7 days.
        set_transient($cache_key, $coords, 7 * DAY_IN_SECONDS);

        // Rate limit: 1 request per second (Nominatim policy).
        sleep(1);

        return $coords;
    }

    /**
     * Build the full address line from endereco + numero.
     */
    private function build_endereco_full($endereco, $numero) {
        if (!empty($endereco)) {
            return empty($numero) ? $endereco : $endereco . ', ' . $numero;
        }
        return $numero ?: '';
    }

    /**
     * AJAX handler: return cities for a given UF (dependent dropdown).
     *
     * Expects POST: nonce, uf_id.
     *
     * @since 1.9.1
     */
    public function handle_get_cidades_por_uf() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'um_dia_no_parque_elementor_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
            return;
        }

        $uf_id = isset($_POST['uf_id']) ? absint($_POST['uf_id']) : 0;
        if ($uf_id < 1) {
            wp_send_json_error(array('message' => __('UF inválida.', 'um-dia-no-parque')));
            return;
        }

        $uc_pt = Um_Dia_No_Parque_Post_Type_UC::get_instance();
        $cidades = $uc_pt->get_municipios_by_uf($uf_id);

        wp_send_json_success(array(
            'cidades' => $cidades,
            'total'   => count($cidades),
        ));
    }

    /**
     * AJAX handler: geocode one UC by ID and cache the result.
     *
     * Expects POST: nonce, uc_id.
     * Returns the coordinates or null.
     *
     * @since 1.9.1
     */
    public function handle_geocode_ucs() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'um_dia_no_parque_elementor_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
            return;
        }

        $uc_id = isset($_POST['uc_id']) ? absint($_POST['uc_id']) : 0;
        if ($uc_id < 1) {
            wp_send_json_error(array('message' => __('UC inválida.', 'um-dia-no-parque')));
            return;
        }

        // Check post exists and is type 'uc'.
        $post = get_post($uc_id);
        if (!$post || 'uc' !== $post->post_type) {
            wp_send_json_error(array('message' => __('UC não encontrada.', 'um-dia-no-parque')));
            return;
        }

        // Gather address parts from meta (cidade from taxonomy canonical).
        $endereco   = get_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_ENDERECO, true);
        $numero     = get_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_NUMERO, true);
        $city_terms = wp_get_object_terms($uc_id, 'cidade', array('fields' => 'all'));
        $municipio  = !empty($city_terms) ? $city_terms[0]->name : '';
        $cep        = get_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_CEP, true);

        // Resolve UF sigla from cidade term's _cidade_uf.
        $uf_sigla  = '';
        if (!empty($city_terms)) {
            $uf_id = (int) get_term_meta($city_terms[0]->term_id, '_cidade_uf', true);
            if ($uf_id > 0) {
                $uf_post = get_post($uf_id);
                if ($uf_post) {
                    $uf_sigla = get_post_meta($uf_post->ID, '_uf_sigla', true);
                }
            }
        }

        // Check if already cached.
        $parts = array_values(array_filter(array(
            $this->build_endereco_full($endereco, $numero),
            $cep,
            $municipio,
            $uf_sigla,
            'Brasil',
        )));
        $address   = implode(', ', $parts);
        $cache_key = 'umdnp_geo_' . md5($address);
        $cached    = get_transient($cache_key);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            wp_send_json_success(array(
                'uc_id' => $uc_id,
                'coords' => $cached,
                'cached' => true,
            ));
            return;
        }

        // Geocode with HTTP call (allow_http=true).
        $coords = $this->geocode_address($endereco, $numero, $municipio, $uf_sigla, $cep, true);

        wp_send_json_success(array(
            'uc_id'  => $uc_id,
            'coords' => $coords,
            'cached' => false,
        ));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Handle manual update check via AJAX.
     *
     * Limpa o cache do GitHub Updater e força uma verificação de
     * atualizações. Retorna a versão disponível e link de update.
     *
     * @since 1.9.0
     */
    /**
     * Handle nonce failure.
     *
     * @since  1.0.0
     * @param  string $context Context description for logging.
     */
    private function handle_nonce_failure($context = '') {
        Um_Dia_No_Parque_Logger::warning('Falha de nonce: ' . $context, array(
            'action' => current_action(),
            'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
        ));
        wp_send_json_error(array('message' => __('Erro de segurança.', 'um-dia-no-parque')));
    }
}

// NOTE: AJAX handlers are registered via Um_Dia_No_Parque_AJAX::get_instance()
// in Um_Dia_No_Parque::load_dependencies() — DO NOT auto-instantiate here.
