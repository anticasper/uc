<?php
/**
 * CPT: Atividades nas UCs
 *
 * @since      1.3.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) exit;

class Um_Dia_No_Parque_Post_Type_Atividades {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'remove_default_tax_metaboxes'), 20);
        add_action('save_post_atividade', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_atividade_posts_columns', array($this, 'set_columns'));
        add_action('manage_atividade_posts_custom_column', array($this, 'column_content'), 10, 2);
        add_filter('manage_edit-atividade_sortable_columns', array($this, 'set_sortable_columns'));

        // Dificuldade — term meta level
        add_action('dificuldade_add_form_fields', array($this, 'dificuldade_level_field_add'));
        add_action('dificuldade_edit_form_fields', array($this, 'dificuldade_level_field_edit'), 10, 2);
        add_action('created_dificuldade', array($this, 'dificuldade_level_save'));
        add_action('edited_dificuldade', array($this, 'dificuldade_level_save'));
        add_filter('manage_edit-dificuldade_columns', array($this, 'dificuldade_level_column'));
        add_filter('manage_dificuldade_custom_column', array($this, 'dificuldade_level_column_content'), 10, 3);
    }

    public function register_post_type() {
        register_post_type('atividade', array(
            'labels' => array(
                'name'               => __('Atividades', 'um-dia-no-parque'),
                'singular_name'      => __('Atividade', 'um-dia-no-parque'),
                'add_new'            => __('Adicionar Nova', 'um-dia-no-parque'),
                'add_new_item'       => __('Adicionar Atividade', 'um-dia-no-parque'),
                'edit_item'          => __('Editar Atividade', 'um-dia-no-parque'),
                'view_item'          => __('Ver Atividade', 'um-dia-no-parque'),
                'all_items'          => __('Atividades', 'um-dia-no-parque'),
                'search_items'       => __('Buscar Atividades', 'um-dia-no-parque'),
                'not_found'          => __('Nenhuma atividade encontrada.', 'um-dia-no-parque'),
                'not_found_in_trash' => __('Nenhuma atividade na lixeira.', 'um-dia-no-parque'),
                'featured_image'     => __('Imagem da Atividade', 'um-dia-no-parque'),
                'set_featured_image' => __('Definir imagem', 'um-dia-no-parque'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'atividades'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 7,
            'menu_icon'          => 'dashicons-universal-access-alt',
            'supports'           => array('title', 'editor', 'excerpt', 'custom-fields'),
        ));
    }

    public function register_taxonomies() {
        // Dificuldade
        register_taxonomy('dificuldade', 'atividade', array(
            'labels' => array(
                'name'          => __('Dificuldades', 'um-dia-no-parque'),
                'singular_name' => __('Dificuldade', 'um-dia-no-parque'),
                'search_items'  => __('Buscar Dificuldades', 'um-dia-no-parque'),
                'all_items'     => __('Todas as Dificuldades', 'um-dia-no-parque'),
                'edit_item'     => __('Editar Dificuldade', 'um-dia-no-parque'),
                'update_item'   => __('Atualizar Dificuldade', 'um-dia-no-parque'),
                'add_new_item'  => __('Adicionar Dificuldade', 'um-dia-no-parque'),
                'menu_name'     => __('Dificuldades', 'um-dia-no-parque'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'dificuldade'),
        ));

        // Público Alvo
        register_taxonomy('publico', 'atividade', array(
            'labels' => array(
                'name'          => __('Públicos', 'um-dia-no-parque'),
                'singular_name' => __('Público', 'um-dia-no-parque'),
                'search_items'  => __('Buscar Públicos', 'um-dia-no-parque'),
                'all_items'     => __('Todos os Públicos', 'um-dia-no-parque'),
                'edit_item'     => __('Editar Público', 'um-dia-no-parque'),
                'update_item'   => __('Atualizar Público', 'um-dia-no-parque'),
                'add_new_item'  => __('Adicionar Público', 'um-dia-no-parque'),
                'menu_name'     => __('Públicos', 'um-dia-no-parque'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'publico'),
        ));

        // Tipo (atividade)
        register_taxonomy('tipo_atividade', 'atividade', array(
            'labels' => array(
                'name'          => __('Tipos de Atividade', 'um-dia-no-parque'),
                'singular_name' => __('Tipo de Atividade', 'um-dia-no-parque'),
                'search_items'  => __('Buscar Tipos', 'um-dia-no-parque'),
                'all_items'     => __('Todos os Tipos', 'um-dia-no-parque'),
                'edit_item'     => __('Editar Tipo', 'um-dia-no-parque'),
                'update_item'   => __('Atualizar Tipo', 'um-dia-no-parque'),
                'add_new_item'  => __('Adicionar Tipo', 'um-dia-no-parque'),
                'menu_name'     => __('Tipos', 'um-dia-no-parque'),
            ),
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'tipo-atividade'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box('atividade_dados', __('Data, Horário e Status', 'um-dia-no-parque'), array($this, 'render'), 'atividade', 'normal', 'high');
    }

    /**
     * Remove os metaboxes padrão das taxonomias para evitar duplicidade
     * com os campos customizados dentro do meta box principal.
     */
    public function remove_default_tax_metaboxes() {
        remove_meta_box('dificuldadediv', 'atividade', 'side');
        remove_meta_box('publicodiv', 'atividade', 'side');
        remove_meta_box('tagsdiv-tipo_atividade', 'atividade', 'side');
    }

    public function render($post) {
        wp_nonce_field('atividade_save', 'atividade_nonce');
        $data       = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA, true);
        $horario    = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::ATIVIDADE_HORARIO, true);
        $descricao  = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::ATIVIDADE_DESCRICAO, true);
        $ativo      = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO, true);

        // Taxonomias selecionadas
        $dif_terms  = get_the_terms($post->ID, 'dificuldade');
        $dif_ids    = $dif_terms ? wp_list_pluck($dif_terms, 'term_id') : array();
        $pub_terms  = get_the_terms($post->ID, 'publico');
        $pub_ids    = $pub_terms ? wp_list_pluck($pub_terms, 'term_id') : array();
        $tipo_terms = get_the_terms($post->ID, 'tipo_atividade');
        $tipo_ids   = $tipo_terms ? wp_list_pluck($tipo_terms, 'term_id') : array();

        // Buscar todos os termos disponíveis
        $todas_dificuldades = get_terms(array('taxonomy' => 'dificuldade', 'hide_empty' => false));
        $todos_publicos     = get_terms(array('taxonomy' => 'publico', 'hide_empty' => false));
        $todos_tipos        = get_terms(array('taxonomy' => 'tipo_atividade', 'hide_empty' => false));
        ?>
        <div class="atividade-meta-box" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-field">
                <label for="atividade_data"><?php _e('Data de Realização', 'um-dia-no-parque'); ?></label>
                <input type="text" id="atividade_data" name="atividade_data" value="<?php echo esc_attr($data); ?>" class="regular-text" placeholder="<?php esc_attr_e('Ex: 19 de julho de 2026', 'um-dia-no-parque'); ?>">
            </div>
            <div class="form-field">
                <label for="atividade_horario"><?php _e('Horário', 'um-dia-no-parque'); ?></label>
                <input type="text" id="atividade_horario" name="atividade_horario" value="<?php echo esc_attr($horario); ?>"
                class="regular-text" placeholder="<?php esc_attr_e('Ex: 08:00', 'um-dia-no-parque'); ?>">
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label for="atividade_descricao"><?php _e('Descrição', 'um-dia-no-parque'); ?></label>
                <textarea id="atividade_descricao" name="atividade_descricao" class="large-text" rows="4"><?php echo esc_textarea($descricao); ?></textarea>
            </div>

            <!-- Taxonomias -->
            <div class="form-field">
                <label><?php _e('Dificuldade', 'um-dia-no-parque'); ?></label>
                <select name="atividade_dificuldade[]" style="width:100%;" multiple size="<?php echo count($todas_dificuldades) > 0 ? min(5, count($todas_dificuldades)) : 3; ?>">
                    <?php foreach ($todas_dificuldades as $term) : ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected(in_array($term->term_id, $dif_ids)); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label><?php _e('Público', 'um-dia-no-parque'); ?></label>
                <select name="atividade_publico[]" style="width:100%;" multiple size="<?php echo count($todos_publicos) > 0 ? min(5, count($todos_publicos)) : 3; ?>">
                    <?php foreach ($todos_publicos as $term) : ?>
                        <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected(in_array($term->term_id, $pub_ids)); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="grid-column:1/-1;">
                <label><?php _e('Tipo de Atividade', 'um-dia-no-parque'); ?></label>
                <div style="max-height:120px;overflow-y:auto;border:1px solid #ddd;padding:6px;border-radius:4px;">
                    <?php if (!empty($todos_tipos)) : ?>
                        <?php foreach ($todos_tipos as $term) : ?>
                            <label style="display:block;font-size:12px;margin:2px 0;">
                                <input type="checkbox" name="atividade_tipo[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array($term->term_id, $tipo_ids)); ?>>
                                <?php echo esc_html($term->name); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="margin:4px 0;color:#999;"><?php _e('Nenhum tipo cadastrado.', 'um-dia-no-parque'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-field">
                <label>
                    <input type="checkbox" name="atividade_ativo" value="1" <?php checked($ativo, '1'); ?>>
                    <?php _e('Ativo', 'um-dia-no-parque'); ?>
                </label>
                <p class="description"><?php _e('Desmarque para ocultar esta atividade do site.', 'um-dia-no-parque'); ?></p>
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['atividade_nonce']) || !wp_verify_nonce(sanitize_key($_POST['atividade_nonce']), 'atividade_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Data
        if (isset($_POST['atividade_data'])) {
            $v = sanitize_text_field(wp_unslash($_POST['atividade_data']));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA);
        }

        // Horario
        if (isset($_POST['atividade_horario'])) {
            $v = sanitize_text_field(wp_unslash($_POST['atividade_horario']));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_HORARIO, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_HORARIO);
        }

        // Descricao
        if (isset($_POST['atividade_descricao'])) {
            $v = sanitize_textarea_field(wp_unslash($_POST['atividade_descricao']));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DESCRICAO, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DESCRICAO);
        }

        // Ativo
        $ativo = isset($_POST['atividade_ativo']) && '1' === $_POST['atividade_ativo'] ? '1' : '0';
        update_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO, $ativo);

        // Taxonomias
        $this->save_taxonomy_terms($post_id, 'atividade_dificuldade', 'dificuldade');
        $this->save_taxonomy_terms($post_id, 'atividade_publico', 'publico');
        $this->save_taxonomy_terms($post_id, 'atividade_tipo', 'tipo_atividade');
    }

    /**
     * Salva os termos de uma taxonomia a partir de um campo POST.
     *
     * @param int    $post_id   Post ID.
     * @param string $field     Nome do campo POST.
     * @param string $taxonomy  Slug da taxonomia.
     */
    private function save_taxonomy_terms($post_id, $field, $taxonomy) {
        if (isset($_POST[$field]) && is_array($_POST[$field])) {
            $term_ids = array_map('intval', $_POST[$field]);
            $term_ids = array_unique(array_filter($term_ids));
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        } else {
            wp_set_object_terms($post_id, array(), $taxonomy);
        }
    }

    public function set_columns($cols) {
        $cols['atividade_data'] = __('Data', 'um-dia-no-parque');
        $cols['atividade_dif']  = __('Dificuldade', 'um-dia-no-parque');
        $cols['atividade_pub']  = __('Público', 'um-dia-no-parque');
        $cols['atividade_tipo'] = __('Tipo', 'um-dia-no-parque');
        $cols['atividade_ativ'] = __('Ativo', 'um-dia-no-parque');
        return $cols;
    }

    public function set_sortable_columns($cols) {
        $cols['atividade_data'] = Um_Dia_No_Parque_Meta::ATIVIDADE_DATA;
        $cols['atividade_ativ'] = Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO;
        return $cols;
    }

    public function column_content($col, $post_id) {
        if ('atividade_data' === $col) {
            echo esc_html(get_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA, true) ?: '—');
        }
        if ('atividade_dif' === $col) {
            $terms = get_the_terms($post_id, 'dificuldade');
            echo $terms ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
        }
        if ('atividade_pub' === $col) {
            $terms = get_the_terms($post_id, 'publico');
            echo $terms ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
        }
        if ('atividade_tipo' === $col) {
            $terms = get_the_terms($post_id, 'tipo_atividade');
            echo $terms ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
        }
        if ('atividade_ativ' === $col) {
            $v = get_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO, true);
            echo '1' === $v ? '<span style="color:#5cb85c;">✓</span>' : '<span style="color:#ccc;">✗</span>';
        }
    }

    // ============================================================
    // Dificuldade — term meta level
    // ============================================================

    /**
     * Campo level no formulário de adicionar dificuldade.
     */
    public function dificuldade_level_field_add() {
        ?>
        <div class="form-field term-level-wrap">
            <label for="dificuldade_level"><?php _e('Nível', 'um-dia-no-parque'); ?></label>
            <input type="number" id="dificuldade_level" name="dificuldade_level" value="" class="small-text" min="1" max="10" step="1">
            <p class="description"><?php _e('Nível de dificuldade (1 = mais fácil, 10 = mais difícil).', 'um-dia-no-parque'); ?></p>
        </div>
        <?php
    }

    /**
     * Campo level no formulário de editar dificuldade.
     */
    public function dificuldade_level_field_edit($term) {
        $level = get_term_meta($term->term_id, Um_Dia_No_Parque_Meta::DIFICULDADE_LEVEL, true);
        ?>
        <tr class="form-field term-level-wrap">
            <th scope="row"><label for="dificuldade_level"><?php _e('Nível', 'um-dia-no-parque'); ?></label></th>
            <td>
                <input type="number" id="dificuldade_level" name="dificuldade_level" value="<?php echo esc_attr($level); ?>" class="small-text" min="1" max="10" step="1">
                <p class="description"><?php _e('Nível de dificuldade (1 = mais fácil, 10 = mais difícil).', 'um-dia-no-parque'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Salva o level da dificuldade.
     */
    public function dificuldade_level_save($term_id) {
        if (!isset($_POST['dificuldade_level'])) {
            return;
        }
        $level = intval($_POST['dificuldade_level']);
        if ($level >= 1 && $level <= 10) {
            update_term_meta($term_id, Um_Dia_No_Parque_Meta::DIFICULDADE_LEVEL, $level);
        } else {
            delete_term_meta($term_id, Um_Dia_No_Parque_Meta::DIFICULDADE_LEVEL);
        }
    }

    /**
     * Adiciona coluna Level na listagem de dificuldades.
     */
    public function dificuldade_level_column($columns) {
        $columns['dificuldade_level'] = __('Nível', 'um-dia-no-parque');
        return $columns;
    }

    /**
     * Conteúdo da coluna Level.
     */
    public function dificuldade_level_column_content($content, $column_name, $term_id) {
        if ('dificuldade_level' === $column_name) {
            $level = get_term_meta($term_id, Um_Dia_No_Parque_Meta::DIFICULDADE_LEVEL, true);
            return $level ? esc_html($level) : '—';
        }
        return $content;
    }
}
