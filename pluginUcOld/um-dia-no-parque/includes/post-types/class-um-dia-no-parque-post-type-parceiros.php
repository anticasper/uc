<?php
/**
 * CPT: Parceiros
 *
 * @since      2.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) exit;

class Um_Dia_No_Parque_Post_Type_Parceiros {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_parceiro', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_parceiro_posts_columns', array($this, 'set_columns'));
        add_action('manage_parceiro_posts_custom_column', array($this, 'column_content'), 10, 2);
    }

    public function register_post_type() {
        register_post_type('parceiro', array(
            'labels' => array(
                'name'               => __('Parceiros', 'um-dia-no-parque'),
                'singular_name'      => __('Parceiro', 'um-dia-no-parque'),
                'add_new'            => __('Adicionar Novo', 'um-dia-no-parque'),
                'add_new_item'       => __('Adicionar Parceiro', 'um-dia-no-parque'),
                'edit_item'          => __('Editar Parceiro', 'um-dia-no-parque'),
                'view_item'          => __('Ver Parceiro', 'um-dia-no-parque'),
                'all_items'          => __('Parceiros', 'um-dia-no-parque'),
                'search_items'       => __('Buscar Parceiros', 'um-dia-no-parque'),
                'not_found'          => __('Nenhum parceiro encontrado.', 'um-dia-no-parque'),
                'not_found_in_trash' => __('Nenhum parceiro na lixeira.', 'um-dia-no-parque'),
                'featured_image'     => __('Logo do Parceiro', 'um-dia-no-parque'),
                'set_featured_image' => __('Definir logo', 'um-dia-no-parque'),
                'remove_featured_image' => __('Remover logo', 'um-dia-no-parque'),
                'use_featured_image' => __('Usar como logo', 'um-dia-no-parque'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'parceiros'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 10,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array('title', 'thumbnail', 'custom-fields'),
        ));
    }

    public function register_taxonomies() {
        register_taxonomy('categoria_parceiro', 'parceiro', array(
            'labels' => array(
                'name'          => __('Categorias de Parceiro', 'um-dia-no-parque'),
                'singular_name' => __('Categoria de Parceiro', 'um-dia-no-parque'),
                'search_items'  => __('Buscar Categorias', 'um-dia-no-parque'),
                'all_items'     => __('Todas as Categorias', 'um-dia-no-parque'),
                'edit_item'     => __('Editar Categoria', 'um-dia-no-parque'),
                'update_item'   => __('Atualizar Categoria', 'um-dia-no-parque'),
                'add_new_item'  => __('Adicionar Categoria', 'um-dia-no-parque'),
                'menu_name'     => __('Categorias', 'um-dia-no-parque'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'categoria-parceiro'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box('parceiro_dados', __('Link do Parceiro', 'um-dia-no-parque'), array($this, 'render'), 'parceiro', 'normal', 'high');
    }

    public function render($post) {
        wp_nonce_field('parceiro_save', 'parceiro_nonce');
        $link = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::PARCEIRO_LINK, true);
        ?>
        <div class="form-field">
            <label for="parceiro_link"><?php _e('URL do Site', 'um-dia-no-parque'); ?></label>
            <input type="url" id="parceiro_link" name="parceiro_link" value="<?php echo esc_attr($link); ?>" class="large-text" placeholder="https://">
            <p class="description"><?php _e('Link externo para o site do parceiro.', 'um-dia-no-parque'); ?></p>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['parceiro_nonce']) || !wp_verify_nonce(sanitize_key($_POST['parceiro_nonce']), 'parceiro_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['parceiro_link'])) {
            $v = esc_url_raw(wp_unslash($_POST['parceiro_link']));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::PARCEIRO_LINK, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::PARCEIRO_LINK);
        }
    }

    public function set_columns($cols) {
        $cols['parceiro_logo'] = __('Logo', 'um-dia-no-parque');
        $cols['parceiro_cat']  = __('Categoria', 'um-dia-no-parque');
        return $cols;
    }

    public function column_content($col, $post_id) {
        if ('parceiro_logo' === $col) {
            echo has_post_thumbnail($post_id) ? '<span style="color:#5cb85c;">✓</span>' : '—';
        }
        if ('parceiro_cat' === $col) {
            $terms = get_the_terms($post_id, 'categoria_parceiro');
            echo $terms ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
        }
    }
}
