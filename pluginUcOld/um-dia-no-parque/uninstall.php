<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/seu-usuario/im-dia-no-parque
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove todas as opções do plugin.
 */
function umdnp_uninstall_remove_options() {
    $options = array(
        'um_dia_no_parque_version',
        'um_dia_no_parque_settings',
        'um_dia_no_parque_pages',
    );

    foreach ($options as $option) {
        delete_option($option);
        delete_site_option($option);
    }
}

/**
 * Remove todos os posts dos CPTs do plugin.
 */
function umdnp_uninstall_remove_cpt_posts() {
    $post_types = array('uc', 'atividade', 'depoimento', 'parceiro', 'uf', 'oque_levar');

    foreach ($post_types as $post_type) {
        $posts = get_posts(array(
            'post_type'      => $post_type,
            'numberposts'    => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        foreach ($posts as $post_id) {
            wp_delete_post($post_id, true);
        }
    }
}

/**
 * Remove arquivos de log criados pelo plugin.
 */
function umdnp_uninstall_remove_logs() {
    $upload_dir = wp_get_upload_dir();
    $log_dir    = $upload_dir['basedir'] . '/umdnp-logs';

    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($log_dir);
    }

    // Remove agendamento cron.
    wp_clear_scheduled_hook('umdnp_log_cleanup');
}

// Executa a limpeza.
umdnp_uninstall_remove_options();
umdnp_uninstall_remove_cpt_posts();
umdnp_uninstall_remove_logs();
