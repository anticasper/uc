<?php
/**
 * CPT: O que levar
 *
 * @since      2.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) exit;

class Um_Dia_No_Parque_Post_Type_OQue_Levar {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_oque_levar', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_oque_levar_posts_columns', array($this, 'set_columns'));
        add_action('manage_oque_levar_posts_custom_column', array($this, 'column_content'), 10, 2);
    }

    public function register_post_type() {
        register_post_type('oque_levar', array(
            'labels' => array(
                'name'               => __('O que levar', 'um-dia-no-parque'),
                'singular_name'      => __('Item', 'um-dia-no-parque'),
                'add_new'            => __('Adicionar', 'um-dia-no-parque'),
                'add_new_item'       => __('Adicionar Item', 'um-dia-no-parque'),
                'edit_item'          => __('Editar Item', 'um-dia-no-parque'),
                'view_item'          => __('Ver Item', 'um-dia-no-parque'),
                'all_items'          => __('O que levar', 'um-dia-no-parque'),
                'search_items'       => __('Buscar Itens', 'um-dia-no-parque'),
                'not_found'          => __('Nenhum item encontrado.', 'um-dia-no-parque'),
                'not_found_in_trash' => __('Nenhum item na lixeira.', 'um-dia-no-parque'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'oque-levar'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 12,
            'menu_icon'          => 'dashicons-cart',
            'supports'           => array('title', 'custom-fields'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box('oque_levar_dados', __('Ícone do Item', 'um-dia-no-parque'), array($this, 'render'), 'oque_levar', 'normal', 'high');
    }

    public function render($post) {
        wp_nonce_field('oque_levar_save', 'oque_levar_nonce');
        $icone = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::OQUE_LEVAR_ICONE, true);
        ?>
        <div class="form-field">
            <label for="oque_levar_icone"><?php _e('Ícone (classe CSS ou URL)', 'um-dia-no-parque'); ?></label>
            <input type="text" id="oque_levar_icone" name="oque_levar_icone" value="<?php echo esc_attr($icone); ?>" class="large-text" placeholder="<?php esc_attr_e('Ex: fa-solid fa-water, dashicons-palmtree', 'um-dia-no-parque'); ?>">
            <p class="description"><?php _e('Classe CSS do ícone (Font Awesome, Dashicons) para exibir junto ao item.', 'um-dia-no-parque'); ?></p>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['oque_levar_nonce']) || !wp_verify_nonce(sanitize_key($_POST['oque_levar_nonce']), 'oque_levar_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['oque_levar_icone'])) {
            $v = sanitize_text_field(wp_unslash($_POST['oque_levar_icone']));
            if (!empty($v)) update_post_meta($post_id, Um_Dia_No_Parque_Meta::OQUE_LEVAR_ICONE, $v);
            else delete_post_meta($post_id, Um_Dia_No_Parque_Meta::OQUE_LEVAR_ICONE);
        }
    }

    public function set_columns($cols) {
        $cols['oque_levar_icone'] = __('Ícone', 'um-dia-no-parque');
        return $cols;
    }

    public function column_content($col, $post_id) {
        if ('oque_levar_icone' === $col) {
            echo esc_html(get_post_meta($post_id, Um_Dia_No_Parque_Meta::OQUE_LEVAR_ICONE, true) ?: '—');
        }
    }
}
