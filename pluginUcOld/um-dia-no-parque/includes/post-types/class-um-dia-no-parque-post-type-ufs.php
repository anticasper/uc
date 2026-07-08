<?php
/**
 * CPT: UFs (Estados)
 *
 * @since      2.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) exit;

class Um_Dia_No_Parque_Post_Type_UFs {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_uf', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_uf_posts_columns', array($this, 'set_columns'));
        add_action('manage_uf_posts_custom_column', array($this, 'column_content'), 10, 2);
        add_filter('manage_edit-uf_sortable_columns', array($this, 'set_sortable_columns'));
    }

    public function register_post_type() {
        register_post_type('uf', array(
            'labels' => array(
                'name'               => __('UFs', 'um-dia-no-parque'),
                'singular_name'      => __('UF', 'um-dia-no-parque'),
                'add_new'            => __('Adicionar', 'um-dia-no-parque'),
                'add_new_item'       => __('Adicionar UF', 'um-dia-no-parque'),
                'edit_item'          => __('Editar UF', 'um-dia-no-parque'),
                'view_item'          => __('Ver UF', 'um-dia-no-parque'),
                'all_items'          => __('UFs', 'um-dia-no-parque'),
                'search_items'       => __('Buscar UFs', 'um-dia-no-parque'),
                'not_found'          => __('Nenhuma UF encontrada.', 'um-dia-no-parque'),
                'not_found_in_trash' => __('Nenhuma UF na lixeira.', 'um-dia-no-parque'),
            ),
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'show_in_rest'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 11,
            'menu_icon'          => 'dashicons-location',
            'supports'           => array('title', 'custom-fields'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box('uf_dados', __('Sigla da UF', 'um-dia-no-parque'), array($this, 'render'), 'uf', 'normal', 'high');
    }

    public function render($post) {
        wp_nonce_field('uf_save', 'uf_nonce');
        $sigla = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::UF_SIGLA, true);
        ?>
        <div class="form-field">
            <label for="uf_sigla"><?php _e('Sigla (2 letras)', 'um-dia-no-parque'); ?></label>
            <input type="text" id="uf_sigla" name="uf_sigla" value="<?php echo esc_attr($sigla); ?>" class="small-text" maxlength="2" placeholder="SP">
        </div>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['uf_nonce']) || !wp_verify_nonce(sanitize_key($_POST['uf_nonce']), 'uf_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['uf_sigla'])) {
            $v = sanitize_text_field(wp_unslash($_POST['uf_sigla']));
            $v = strtoupper(substr($v, 0, 2));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::UF_SIGLA, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::UF_SIGLA);
        }
    }

    public function set_columns($cols) {
        $cols['uf_sigla'] = __('Sigla', 'um-dia-no-parque');
        return $cols;
    }

    public function set_sortable_columns($cols) {
        $cols['uf_sigla'] = Um_Dia_No_Parque_Meta::UF_SIGLA;
        return $cols;
    }

    public function column_content($col, $post_id) {
        if ('uf_sigla' === $col) {
            echo esc_html(get_post_meta($post_id, Um_Dia_No_Parque_Meta::UF_SIGLA, true) ?: '—');
        }
    }
}
