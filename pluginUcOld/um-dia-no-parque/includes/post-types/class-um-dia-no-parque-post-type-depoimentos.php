<?php
/**
 * CPT: Depoimentos de Visitantes
 *
 * @since      1.3.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes/post-types
 */

if (!defined('ABSPATH')) exit;

class Um_Dia_No_Parque_Post_Type_Depoimentos {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_depoimento', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_depoimento_posts_columns', array($this, 'set_columns'));
        add_action('manage_depoimento_posts_custom_column', array($this, 'column_content'), 10, 2);
    }

    public function register_post_type() {
        register_post_type('depoimento', array(
            'labels' => array(
                'name'               => __('Depoimentos', 'um-dia-no-parque'),
                'singular_name'      => __('Depoimento', 'um-dia-no-parque'),
                'add_new'            => __('Adicionar Novo', 'um-dia-no-parque'),
                'add_new_item'       => __('Adicionar Depoimento', 'um-dia-no-parque'),
                'edit_item'          => __('Editar Depoimento', 'um-dia-no-parque'),
                'view_item'          => __('Ver Depoimento', 'um-dia-no-parque'),
                'all_items'          => __('Depoimentos', 'um-dia-no-parque'),
                'search_items'       => __('Buscar Depoimentos', 'um-dia-no-parque'),
                'not_found'          => __('Nenhum depoimento encontrado.', 'um-dia-no-parque'),
                'not_found_in_trash' => __('Nenhum depoimento na lixeira.', 'um-dia-no-parque'),
                'featured_image'     => __('Foto do Visitante', 'um-dia-no-parque'),
                'set_featured_image' => __('Definir foto', 'um-dia-no-parque'),
                'remove_featured_image' => __('Remover foto', 'um-dia-no-parque'),
                'use_featured_image' => __('Usar como foto', 'um-dia-no-parque'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'depoimentos'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'menu_position'      => 8,
            'menu_icon'          => 'dashicons-testimonial',
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box('depoimento_dados', __('Mídia do Depoimento', 'um-dia-no-parque'), array($this, 'render'), 'depoimento', 'normal', 'high');
    }

    public function render($post) {
        wp_nonce_field('depoimento_save', 'depoimento_nonce');
        $url_video = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::DEPOIMENTO_URL_VIDEO, true);
        $upload_id = get_post_meta($post->ID, Um_Dia_No_Parque_Meta::DEPOIMENTO_UPLOAD, true);
        ?>
        <div class="depoimento-meta-box">
            <div class="form-field">
                <label for="depoimento_url_video"><?php _e('URL do Vídeo (YouTube/Vimeo)', 'um-dia-no-parque'); ?></label>
                <input type="url" id="depoimento_url_video" name="depoimento_url_video" value="<?php echo esc_attr($url_video); ?>" class="large-text" placeholder="https://">
                <p class="description"><?php _e('Link para vídeo externo (YouTube, Vimeo, etc.)', 'um-dia-no-parque'); ?></p>
            </div>
            <div class="form-field">
                <label><?php _e('Upload de Vídeo ou Foto', 'um-dia-no-parque'); ?></label>
                <div class="umdnp-image-field">
                    <input type="hidden" class="umdnp-image-id" name="depoimento_upload" value="<?php echo esc_attr($upload_id); ?>">
                    <button type="button" class="button umdnp-upload-image-btn"><?php echo $upload_id ? __('Trocar Mídia', 'um-dia-no-parque') : __('Selecionar Mídia', 'um-dia-no-parque'); ?></button>
                    <?php if ($upload_id) : ?>
                        <button type="button" class="button umdnp-remove-image-btn" style="margin-left:4px;"><?php _e('Remover', 'um-dia-no-parque'); ?></button>
                    <?php endif; ?>
                    <div class="umdnp-image-preview" style="margin-top:8px;">
                        <?php if ($upload_id) : ?>
                            <?php $src = wp_get_attachment_image_url($upload_id, 'medium'); ?>
                            <?php if ($src) : ?>
                                <img src="<?php echo esc_url($src); ?>" style="max-width:200px;border-radius:4px;">
                            <?php else : ?>
                                <p><em><?php echo esc_html__('Arquivo selecionado (ID: ', 'um-dia-no-parque') . esc_html($upload_id) . ')</em></p>'; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['depoimento_nonce']) || !wp_verify_nonce(sanitize_key($_POST['depoimento_nonce']), 'depoimento_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // URL do vídeo
        if (isset($_POST['depoimento_url_video'])) {
            $v = esc_url_raw(wp_unslash($_POST['depoimento_url_video']));
            if (!empty($v)) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_URL_VIDEO, $v);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_URL_VIDEO);
            }
        }

        // Upload (attachment ID)
        if (isset($_POST['depoimento_upload'])) {
            $v = intval($_POST['depoimento_upload']);
            if ($v > 0) {
                update_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_UPLOAD, $v);
            } else {
                delete_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_UPLOAD);
            }
        }
    }

    public function set_columns($cols) {
        $cols['depoimento_tem_video'] = __('Vídeo', 'um-dia-no-parque');
        $cols['depoimento_tem_foto']  = __('Mídia', 'um-dia-no-parque');
        return $cols;
    }

    public function column_content($col, $post_id) {
        if ('depoimento_tem_video' === $col) {
            $url = get_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_URL_VIDEO, true);
            echo $url ? '<span style="color:#5cb85c;">✓</span>' : '—';
        }
        if ('depoimento_tem_foto' === $col) {
            $id = get_post_meta($post_id, Um_Dia_No_Parque_Meta::DEPOIMENTO_UPLOAD, true);
            echo $id ? '<span style="color:#5cb85c;">✓</span>' : '—';
        }
    }
}
