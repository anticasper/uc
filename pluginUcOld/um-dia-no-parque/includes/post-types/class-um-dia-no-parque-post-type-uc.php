<?php
/**
 * Custom Post Type: Unidades de Conservação (UC)
 *
 * @since      1.2.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) {
    exit;
}

class Um_Dia_No_Parque_Post_Type_UC {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('init', array($this, 'register_cidade_term_meta'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_uc', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_uc_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_uc_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-uc_sortable_columns', array($this, 'set_sortable_columns'));
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => __('Unidades de Conservação', 'um-dia-no-parque'),
            'singular_name'         => __('Unidade de Conservação', 'um-dia-no-parque'),
            'menu_name'             => __('U. Conservação', 'um-dia-no-parque'),
            'name_admin_bar'        => __('UC', 'um-dia-no-parque'),
            'add_new'               => __('Adicionar Nova', 'um-dia-no-parque'),
            'add_new_item'          => __('Adicionar Nova UC', 'um-dia-no-parque'),
            'new_item'              => __('Nova UC', 'um-dia-no-parque'),
            'edit_item'             => __('Editar UC', 'um-dia-no-parque'),
            'view_item'             => __('Ver UC', 'um-dia-no-parque'),
            'all_items'             => __('Todas as UCs', 'um-dia-no-parque'),
            'search_items'          => __('Buscar UCs', 'um-dia-no-parque'),
            'not_found'             => __('Nenhuma UC encontrada.', 'um-dia-no-parque'),
            'not_found_in_trash'    => __('Nenhuma UC encontrada na lixeira.', 'um-dia-no-parque'),
            'featured_image'        => __('Imagem da UC', 'um-dia-no-parque'),
            'set_featured_image'    => __('Definir imagem da UC', 'um-dia-no-parque'),
            'remove_featured_image' => __('Remover imagem da UC', 'um-dia-no-parque'),
            'use_featured_image'    => __('Usar como imagem da UC', 'um-dia-no-parque'),
        );

        $args = array(
            'labels'              => $labels,
            'description'         => __('Unidades de Conservação ambientais', 'um-dia-no-parque'),
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'unidades-de-conservacao'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-palmtree',
            'supports'            => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'),
            'taxonomies'          => array('cidade'),
        );
        register_post_type('uc', $args);
    }

    public function register_taxonomies() {
        // Bioma
        $labels = array(
            'name'              => __('Biomas', 'um-dia-no-parque'),
            'singular_name'     => __('Bioma', 'um-dia-no-parque'),
            'search_items'      => __('Buscar Biomas', 'um-dia-no-parque'),
            'all_items'         => __('Todos os Biomas', 'um-dia-no-parque'),
            'parent_item'       => __('Bioma Pai', 'um-dia-no-parque'),
            'parent_item_colon' => __('Bioma Pai:', 'um-dia-no-parque'),
            'edit_item'         => __('Editar Bioma', 'um-dia-no-parque'),
            'update_item'       => __('Atualizar Bioma', 'um-dia-no-parque'),
            'add_new_item'      => __('Adicionar Novo Bioma', 'um-dia-no-parque'),
            'new_item_name'     => __('Nome do Novo Bioma', 'um-dia-no-parque'),
            'menu_name'         => __('Biomas', 'um-dia-no-parque'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'bioma'),
        );
        register_taxonomy('bioma', 'uc', $args);

        // Cidade — não hierárquica, sem UI admin (gerenciada via import/migration)
        $cidade_labels = array(
            'name'              => __('Cidades', 'um-dia-no-parque'),
            'singular_name'     => __('Cidade', 'um-dia-no-parque'),
            'search_items'      => __('Buscar Cidades', 'um-dia-no-parque'),
            'all_items'         => __('Todas as Cidades', 'um-dia-no-parque'),
            'edit_item'         => __('Editar Cidade', 'um-dia-no-parque'),
            'update_item'       => __('Atualizar Cidade', 'um-dia-no-parque'),
            'add_new_item'      => __('Adicionar Nova Cidade', 'um-dia-no-parque'),
            'new_item_name'     => __('Nome da Nova Cidade', 'um-dia-no-parque'),
            'menu_name'         => __('Cidades', 'um-dia-no-parque'),
        );

        $cidade_args = array(
            'labels'            => $cidade_labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => false, // gerenciada via import
            'show_admin_column' => false,
            'show_in_nav_menus' => false,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'cidade'),
        );
        register_taxonomy('cidade', 'uc', $cidade_args);
    }

    /**
     * Register term meta for cidade → UF relationship.
     *
     * @since 1.9.1
     */
    public function register_cidade_term_meta() {
        register_term_meta('cidade', '_cidade_uf', array(
            'type'              => 'integer',
            'description'       => __('ID do post UF ao qual esta cidade pertence.', 'um-dia-no-parque'),
            'single'            => true,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ));
    }

    // ============================================================
    // META BOXES
    // ============================================================

    public function add_meta_boxes() {
        add_meta_box(
            'uc_dados',
            __('Dados da UC', 'um-dia-no-parque'),
            array($this, 'render_dados'),
            'uc',
            'normal',
            'high'
        );
    }

    /**
     * Renderiza metabox com campos da UC.
     */
    public function render_dados($post) {
        wp_nonce_field('uc_dados_nonce', 'uc_dados_nonce_field');

        $responsavel   = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_RESPONSAVEL, true);
        $email         = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_EMAIL, true);
        $whatsapp      = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_WHATSAPP, true);
        $realizador    = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_REALIZADOR, true);
        $breve_descricao = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO, true);
        $cep           = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_CEP, true);
        $endereco      = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_ENDERECO, true);
        $numero        = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_NUMERO, true);
        $link_endereco = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_LINK_ENDERECO, true);
        $social        = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_SOCIAL, true);
        $imagem_id     = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_IMAGEM, true);

        // Cidade — lê da taxonomia (canonical)
        $cidade_terms = wp_get_object_terms($post->ID, 'cidade', array('fields' => 'ids'));
        $cidade_nome   = '';
        $uf_selected_id = 0;
        if (!empty($cidade_terms) && !is_wp_error($cidade_terms)) {
            $term = get_term($cidade_terms[0]);
            if ($term) {
                $cidade_nome = $term->name;
            }
            $uf_selected_id = (int) get_term_meta($cidade_terms[0], '_cidade_uf', true);
        }
        $ufs             = get_posts(array('post_type' => 'uf', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <div class="uc-meta-box" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-field">
                <label for="uc_responsavel"><?php _e('Responsável pela Atividade', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_responsavel" name="uc_responsavel" value="<?php echo esc_attr($responsavel); ?>" class="regular-text">
            </div>
            <div class="form-field">
                <label for="uc_email"><?php _e('Email', 'um-dia-no-parque'); ?></label>
                <input type="email" id="uc_email" name="uc_email" value="<?php echo esc_attr($email); ?>" class="regular-text">
            </div>
            <div class="form-field">
                <label for="uc_whatsapp"><?php _e('WhatsApp', 'um-dia-no-parque'); ?></label>
                <input type="tel" id="uc_whatsapp" name="uc_whatsapp" value="<?php echo esc_attr($whatsapp); ?>" class="regular-text">
            </div>
            <div class="form-field">
                <label for="uc_realizador"><?php _e('Realizador da Atividade', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_realizador" name="uc_realizador" value="<?php echo esc_attr($realizador); ?>"
                       class="regular-text">
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label for="uc_breve_descricao"><?php _e('Breve Descrição', 'um-dia-no-parque'); ?></label>
                <textarea id="uc_breve_descricao" name="uc_breve_descricao" class="large-text" rows="3"><?php echo esc_textarea($breve_descricao); ?></textarea>
            </div>
            <div class="form-field">
                <label for="uc_uf_id"><?php _e('Estado', 'um-dia-no-parque'); ?></label>
                <select id="uc_uf_id" name="uc_uf_id" class="regular-text">
                    <option value=""><?php _e('— Selecione o estado —', 'um-dia-no-parque'); ?></option>
                    <?php foreach ($ufs as $uf) :
                        $sigla = get_post_meta($uf->ID, Um_Dia_No_Parque_Meta::UF_SIGLA, true);
                    ?>
                        <option value="<?php echo esc_attr($uf->ID); ?>" <?php selected($uf_selected_id, $uf->ID); ?>>
                            <?php echo esc_html($sigla ? $uf->post_title . ' (' . $sigla . ')' : $uf->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="uc_municipio"><?php _e('Município', 'um-dia-no-parque'); ?></label>
                <select id="uc_municipio" name="uc_municipio" class="regular-text">
                    <option value=""><?php _e('— Selecione o estado primeiro —', 'um-dia-no-parque'); ?></option>
                </select>
                <input type="hidden" name="uc_municipio_term_id" id="uc_municipio_term_id" value="">
            </div>
            <script>
            (function(){
                var ufSelect = document.getElementById('uc_uf_id');
                var cidadeSelect = document.getElementById('uc_municipio');
                if (!ufSelect || !cidadeSelect) return;
                var prevVal = '<?php echo esc_js($cidade_nome); ?>';
                ufSelect.addEventListener('change', function(){
                    var ufId = this.value;
                    if (!ufId) {
                        cidadeSelect.innerHTML = '<option value=""><?php echo esc_js(__('— Selecione o estado primeiro —', 'um-dia-no-parque')); ?></option>';
                        return;
                    }
                    cidadeSelect.innerHTML = '<option value=""><?php echo esc_js(__('Carregando...', 'um-dia-no-parque')); ?></option>';
                    var data = new FormData();
                    data.append('action', 'umdnp_get_cidades_por_uf');
                    data.append('nonce', '<?php echo esc_js(wp_create_nonce('um_dia_no_parque_elementor_nonce')); ?>');
                    data.append('uf_id', ufId);
                    fetch(ajaxurl, { method: 'POST', body: data })
                        .then(function(r){ return r.json(); })
                        .then(function(resp){
                            if (!resp.success || !resp.data || !resp.data.cidades) {
                                cidadeSelect.innerHTML = '<option value=""><?php echo esc_js(__('— Nenhuma cidade encontrada —', 'um-dia-no-parque')); ?></option>';
                                return;
                            }
                            var html = '<option value=""><?php echo esc_js(__('— Selecione a cidade —', 'um-dia-no-parque')); ?></option>';
                            for (var j = 0; j < resp.data.cidades.length; j++) {
                                var c = resp.data.cidades[j];
                                var sel = c === prevVal ? ' selected' : '';
                                html += '<option value="' + c.replace(/"/g, '&quot;') + '"' + sel + '>' + c + '</option>';
                            }
                            cidadeSelect.innerHTML = html;
                        })
                        .catch(function(){
                            cidadeSelect.innerHTML = '<option value=""><?php echo esc_js(__('— Erro ao carregar —', 'um-dia-no-parque')); ?></option>';
                        });
                });
                // Se já há UF selecionado (edição), carrega cidades automaticamente
                if (ufSelect.value) {
                    ufSelect.dispatchEvent(new Event('change'));
                }
            })();
            </script>
            <div class="form-field">
                <label for="uc_cep"><?php _e('CEP', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_cep" name="uc_cep" value="<?php echo esc_attr($cep); ?>" class="regular-text">
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label for="uc_endereco"><?php _e('Endereço', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_endereco" name="uc_endereco" value="<?php echo esc_attr($endereco); ?>" class="large-text">
            </div>
            <div class="form-field">
                <label for="uc_numero"><?php _e('Número', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_numero" name="uc_numero" value="<?php echo esc_attr($numero); ?>" class="regular-text">
            </div>
            <div class="form-field">
                <label for="uc_link_endereco"><?php _e('Link do Endereço (Google Maps)', 'um-dia-no-parque'); ?></label>
                <input type="url" id="uc_link_endereco" name="uc_link_endereco" value="<?php echo esc_attr($link_endereco); ?>"
                       class="regular-text" placeholder="https://">
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label for="uc_social"><?php _e('Redes Sociais', 'um-dia-no-parque'); ?></label>
                <input type="text" id="uc_social" name="uc_social" value="<?php echo esc_attr($social); ?>" class="large-text"
                       placeholder="Instagram, Facebook, site…">
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label><?php _e('Imagem da UC', 'um-dia-no-parque'); ?></label>
                <div class="umdnp-image-field">
                    <input type="hidden" class="umdnp-image-id" name="uc_imagem" value="<?php echo esc_attr($imagem_id); ?>">
                    <button type="button"
                            class="button umdnp-upload-image-btn"><?php echo $imagem_id ? __('Trocar Imagem', 'um-dia-no-parque') : __('Selecionar Imagem', 'um-dia-no-parque'); ?></button>
                    <?php if ($imagem_id) : ?>
                        <button type="button" class="button umdnp-remove-image-btn"
                                style="margin-left:4px;"><?php _e('Remover', 'um-dia-no-parque'); ?></button>
                    <?php endif; ?>
                    <div class="umdnp-image-preview" style="margin-top:8px;">
                        <?php if ($imagem_id) : ?>
                            <?php $src = wp_get_attachment_image_url($imagem_id, 'medium'); ?>
                            <?php if ($src) : ?>
                                <img src="<?php echo esc_url($src); ?>" style="max-width:300px;border-radius:4px;">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            // Atividades
            $atv_ids = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS, true) ?: array();
            $atvs    = get_posts(array('post_type' => 'atividade', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
            ?>
            <div class="form-field" style="grid-column:1/-1;">
                <label><?php esc_html_e('Atividades', 'um-dia-no-parque'); ?></label>
                <input type="text" class="umdnp-rel-search"
                       placeholder="<?php esc_attr_e('Buscar atividade...', 'um-dia-no-parque'); ?>"
                       style="width:100%;margin-bottom:4px;padding:4px 6px;font-size:12px;box-sizing:border-box;">
                <div style="max-height:160px;overflow-y:auto;border:1px solid #ddd;padding:6px 8px;border-radius:4px;background:#fff;">
                    <?php foreach ($atvs as $a) : ?>
                        <label style="display:block;font-size:12px;margin:3px 0;cursor:pointer;">
                            <input type="checkbox" name="uc_atividade_ids[]"
                                   value="<?php echo esc_attr($a->ID); ?>" <?php checked(in_array($a->ID, $atv_ids)); ?>>
                            <?php echo esc_html($a->post_title); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <script>
                (function(){
                    var s = document.querySelector('.umdnp-rel-search');
                    if (!s) return;
                    s.addEventListener('input', function(){
                        var q = this.value.toLowerCase();
                        var lbs = this.parentNode.querySelectorAll('label');
                        for (var i = 0; i < lbs.length; i++)
                            lbs[i].style.display = lbs[i].textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                    });
                })();
                </script>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // SAVE
    // ============================================================

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['uc_dados_nonce_field'])
            || !wp_verify_nonce(sanitize_key($_POST['uc_dados_nonce_field']), 'uc_dados_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Campos de texto (exclui municipio — agora é taxonomia)
        $text_fields = array(
            'uc_responsavel'    => Um_Dia_No_Parque_Meta::UC_RESPONSAVEL,
            'uc_email'          => Um_Dia_No_Parque_Meta::UC_EMAIL,
            'uc_whatsapp'       => Um_Dia_No_Parque_Meta::UC_WHATSAPP,
            'uc_realizador'     => Um_Dia_No_Parque_Meta::UC_REALIZADOR,
            'uc_cep'            => Um_Dia_No_Parque_Meta::UC_CEP,
            'uc_endereco'       => Um_Dia_No_Parque_Meta::UC_ENDERECO,
            'uc_numero'         => Um_Dia_No_Parque_Meta::UC_NUMERO,
            'uc_social'         => Um_Dia_No_Parque_Meta::UC_SOCIAL,
        );

        foreach ($text_fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                if (!empty($value)) {
                    update_post_meta($post_id, $meta_key, $value);
                } else {
                    delete_post_meta($post_id, $meta_key);
                }
            }
        }

        // Breve descrição (textarea — preserva quebras de linha)
        if (isset($_POST['uc_breve_descricao'])) {
            $value = sanitize_textarea_field(wp_unslash($_POST['uc_breve_descricao']));
            if (!empty($value)) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO, $value);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO);
            }
        }

        // Cidade — salva como taxonomia
        if (isset($_POST['uc_municipio'])) {
            $cidade_nome = sanitize_text_field(wp_unslash($_POST['uc_municipio']));
            if (!empty($cidade_nome)) {
                // Get or create the term
                $term = term_exists($cidade_nome, 'cidade');
                if (!$term) {
                    $term = wp_insert_term($cidade_nome, 'cidade');
                }
                if (!is_wp_error($term) && isset($term['term_id'])) {
                    wp_set_object_terms($post_id, array((int) $term['term_id']), 'cidade', false);

                    // Link cidade → UF via term meta
                    $uf_id = isset($_POST['uc_uf_id']) ? absint($_POST['uc_uf_id']) : 0;
                    if ($uf_id > 0) {
                        $current_uf = get_term_meta($term['term_id'], '_cidade_uf', true);
                        if (empty($current_uf)) {
                            update_term_meta($term['term_id'], '_cidade_uf', $uf_id);
                        }
                    }
                }
            } else {
                wp_set_object_terms($post_id, array(), 'cidade', false);
            }
        }

        // Imagem (attachment ID)
        if (isset($_POST['uc_imagem'])) {
            $value = intval($_POST['uc_imagem']);
            if ($value > 0) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_IMAGEM, $value);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_IMAGEM);
            }
        }

        // URL do link
        if (isset($_POST['uc_link_endereco'])) {
            $value = esc_url_raw(wp_unslash($_POST['uc_link_endereco']));
            if (!empty($value)) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_LINK_ENDERECO, $value);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_LINK_ENDERECO);
            }
        }

        // Relacionamentos — Atividades
        if (isset($_POST['uc_atividade_ids'])) {
            $ids = isset($_POST['uc_atividade_ids']) ? array_map('intval', (array) $_POST['uc_atividade_ids']) : array();
            $ids = array_unique(array_filter($ids));
            if (!empty($ids)) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS, $ids);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS);
            }
        }
    }

    // ============================================================
    // ADMIN COLUMNS
    // ============================================================

    public function set_custom_columns($columns) {
        $columns['uc_breve_descricao'] = __('Breve Descrição', 'um-dia-no-parque');
        $columns['uc_municipio']       = __('Município', 'um-dia-no-parque');
        $columns['uc_bioma']           = __('Bioma', 'um-dia-no-parque');
        $columns['uc_imagem']          = __('Imagem', 'um-dia-no-parque');
        return $columns;
    }

    public function set_sortable_columns($columns) {
        $columns['uc_municipio'] = 'uc_municipio';
        return $columns;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'uc_breve_descricao':
                $val = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO, true);
                echo $val ? esc_html(wp_trim_words($val, 15, '…')) : '—';
                break;
            case 'uc_municipio':
                $terms = wp_get_object_terms($post_id, 'cidade', array('fields' => 'names'));
                echo !empty($terms) ? esc_html($terms[0]) : '—';
                break;
            case 'uc_bioma':
                $terms = get_the_terms($post_id, 'bioma');
                echo $terms ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
                break;
            case 'uc_imagem':
                $img_id = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_IMAGEM, true);
                if ($img_id) {
                    $src = wp_get_attachment_image_url($img_id, 'thumbnail');
                    echo $src ? '<img src="' . esc_url($src) . '" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">' : '—';
                } else {
                    echo '—';
                }
                break;
        }
    }

    // ============================================================
    // CIDADE HELPERS — taxonomy-based
    /**
     * Get city terms linked to a specific UF via term meta.
     *
     * @since  1.9.1
     * @param  int  $uf_id Post ID of the UF.
     * @return array        Array of city names.
     */
    public function get_municipios_by_uf(int $uf_id): array {
        $terms = get_terms(array(
            'taxonomy'   => 'cidade',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'meta_query' => array(
                array(
                    'key'   => '_cidade_uf',
                    'value' => $uf_id,
                    'type'  => 'NUMERIC',
                ),
            ),
        ));
        if (is_wp_error($terms) || empty($terms)) {
            return array();
        }
        return wp_list_pluck($terms, 'name');
    }

    /**
     * Find or create a cidade term, linking it to a UF.
     *
     * @since  1.9.1
     * @param  string $nome  City name.
     * @param  int    $uf_id UF post ID (0 to skip linking).
     * @return int           Term ID, 0 on failure.
     */
    public function find_or_create_cidade(string $nome, int $uf_id = 0): int {
        $nome = sanitize_text_field(trim($nome));
        if (empty($nome)) {
            return 0;
        }

        $term = term_exists($nome, 'cidade');
        if ($term && isset($term['term_id'])) {
            $term_id = (int) $term['term_id'];
        } else {
            $result = wp_insert_term($nome, 'cidade');
            if (is_wp_error($result) || !isset($result['term_id'])) {
                return 0;
            }
            $term_id = (int) $result['term_id'];
        }

        // Link to UF if not already set.
        if ($uf_id > 0) {
            $current_uf = get_term_meta($term_id, '_cidade_uf', true);
            if (empty($current_uf)) {
                update_term_meta($term_id, '_cidade_uf', $uf_id);
            }
        }

        return $term_id;
    }

    /**
     * Migrate existing _uc_municipio post meta to cidade taxonomy.
     *
     * Called on plugin update. Idempotent — skips UCs that already
     * have a cidade term.
     *
     * @since  1.9.1
     */
    public function migrate_municipio_to_taxonomy(): void {
        global $wpdb;

        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value != ''
             AND p.ID NOT IN (
                 SELECT object_id FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tt.taxonomy = 'cidade'
             )",
            'uc',
            '_uc_municipio'
        ));

        foreach ($post_ids as $post_id) {
            $municipio = get_post_meta($post_id, '_uc_municipio', true);
            if (empty($municipio)) {
                continue;
            }

            // Get UF from existing meta.
            $uf_ids = get_post_meta($post_id, '_uc_uf_ids', true) ?: array();
            $uf_id  = !empty($uf_ids) && is_array($uf_ids) ? (int) reset($uf_ids) : 0;

            $term_id = $this->find_or_create_cidade($municipio, $uf_id);
            if ($term_id > 0) {
                wp_set_object_terms($post_id, array($term_id), 'cidade', true);
            }
        }
    }
}
