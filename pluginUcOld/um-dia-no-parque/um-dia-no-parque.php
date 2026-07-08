<?php
/**
 * Plugin Name: Um dia No Parque
 * Plugin URI:  https://github.com/edisonjulianoti/um-dia-no-parque
 * Description: Plugin para gerenciamento de parques, eventos e atividades do Dia no Parque.
 * Version:           1.9.1
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Edison Julianoti
 * Author URI:        https://ejulianoti.com.br
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       um-dia-no-parque
 * Domain Path:       /languages
 *
 * @package           Um_Dia_No_Parque
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('ABSPATH')) {
    die;
}

// Definição de constantes do plugin.
define('UM_DIA_NO_PARQUE_VERSION', '1.9.1');
define('UM_DIA_NO_PARQUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UM_DIA_NO_PARQUE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Autoloader para classes com prefixo Um_Dia_No_Parque_.
 *
 * Mapeia o nome da classe para o arquivo correspondente seguindo o padrão:
 *   Um_Dia_No_Parque_Post_Type_Eventos → includes/post-types/class-um-dia-no-parque-post-type-eventos.php
 *   Um_Dia_No_Parque_AJAX             → includes/class-um-dia-no-parque-ajax.php
 *   Um_Dia_No_Parque_Elementor        → elementor/class-um-dia-no-parque-elementor.php
 *
 * @since  1.6.0
 * @param  string $class Nome completo da classe.
 * @return void
 */
spl_autoload_register(function ($class) {
    $prefix = 'Um_Dia_No_Parque_';
    $len    = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = strtolower(substr($class, $len));
    $relative_class = str_replace('_', '-', $relative_class);

    $paths = array(
        "includes/post-types/class-um-dia-no-parque-{$relative_class}.php",
        "includes/class-um-dia-no-parque-{$relative_class}.php",
        "admin/class-um-dia-no-parque-{$relative_class}.php",
        "elementor/class-um-dia-no-parque-{$relative_class}.php",
    );

    foreach ($paths as $path) {
        $file = UM_DIA_NO_PARQUE_PLUGIN_DIR . $path;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * O plugin declara 'Text Domain: um-dia-no-parque' e 'Domain Path: /languages'
 * no cabeçalho principal. Desde WordPress 4.6, isto é suficiente para que o
 * WordPress carregue as traduções automaticamente no momento certo (after_setup_theme).
 *
 * O filtro abaixo suprime silenciosamente o notice _load_textdomain_just_in_time
 * do WP 6.7+ para cenários extremos onde uma chamada __() seja invocada
 * antes de after_setup_theme (ex.: por um terceiro que chame get_plugin_data()
 * cedo demais). A supressão é segura pois o carregamento JIT continua
 * funcionando normalmente.
 *
 * @since  1.1.0
 */
add_filter(
    'doing_it_wrong_trigger_error',
    function ($trigger, $function_name, $message) {
        if (
            '_load_textdomain_just_in_time' === $function_name
            && false !== strpos($message, "'um-dia-no-parque'")
        ) {
            return false;
        }
        return $trigger;
    },
    10,
    3
);

/**
 * Código executado na ativação do plugin.
 *
 * NOTA: flush_rewrite_rules() NÃO é chamado aqui porque neste ponto
 * o hook 'init' ainda não rodou e os CPTs ainda não foram registrados.
 * Usamos um transient para adiar o flush para depois do registro dos CPTs.
 */
function activate_um_dia_no_parque() {
    update_option('um_dia_no_parque_version', UM_DIA_NO_PARQUE_VERSION);

    // Flag to flush rewrite rules on next request, after CPTs are registered.
    set_transient('um_dia_no_parque_flush_rewrite_rules', true);
}

/**
 * Código executado na desativação do plugin.
 */
function deactivate_um_dia_no_parque() {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'activate_um_dia_no_parque');
register_deactivation_hook(__FILE__, 'deactivate_um_dia_no_parque');

/**
 * Flush das rewrite rules adiado: executa na primeira requisição após
 * a ativação do plugin, garantindo que os CPTs já estejam registrados.
 *
 * O transient é definido em activate_um_dia_no_parque() e consumido
 * aqui no init com prioridade 20 (após o registro dos CPTs em init:10).
 */
add_action('init', function () {
    if (get_transient('um_dia_no_parque_flush_rewrite_rules')) {
        flush_rewrite_rules(false);
        delete_transient('um_dia_no_parque_flush_rewrite_rules');
    }
}, 20);

/**
 * Seed: todos os 27 estados + 5.570+ municípios do Brasil.
 * Executa em admin_init na primeira carga após ativação/update.
 * Só marca como seeded se o seed criou termos de fato.
 */
add_action('admin_init', function () {
    if (get_option('umdnp_cidades_seeded', false)) {
        return;
    }

    // Quick check: already have cidade terms? Then flag is just stale.
    $term_count = wp_count_terms(array(
        'taxonomy'   => 'cidade',
        'hide_empty' => false,
    ));
    if (!is_wp_error($term_count) && $term_count > 0) {
        update_option('umdnp_cidades_seeded', true);
        return;
    }

    // Try to seed.
    if (class_exists('Um_Dia_No_Parque_Seed')) {
        $seed = Um_Dia_No_Parque_Seed::get_instance();
        $seed->seed_all();
    }

    // Verify: only mark seeded if terms were actually created.
    $term_count = wp_count_terms(array(
        'taxonomy'   => 'cidade',
        'hide_empty' => false,
    ));
    if (!is_wp_error($term_count) && $term_count > 0) {
        update_option('umdnp_cidades_seeded', true);
    }
    // If still zero, leave flag unset so it retries on next admin pageload.
});

/**
 * Inicializa o plugin.
 */
require UM_DIA_NO_PARQUE_PLUGIN_DIR . 'includes/class-um-dia-no-parque.php';

/**
 * Render callback para o bloco Hero (server-side rendering, sem Node.js).
 *
 * @since  1.8.1
 * @param  array    $attributes Block attributes.
 * @param  string   $content    Block content.
 * @param  WP_Block $block      Block instance.
 * @return string
 */
function um_dia_no_parque_render_hero_block($attributes, $content, $block) {
    $title    = isset($attributes['title']) ? $attributes['title'] : __('Um Dia no Parque', 'um-dia-no-parque');
    $subtitle = isset($attributes['subtitle']) ? $attributes['subtitle'] : '';

    $wrapper_attributes = get_block_wrapper_attributes();

    $output = '<div ' . $wrapper_attributes . '>';
    $output .= '<h1>' . esc_html($title) . '</h1>';
    if (!empty($subtitle)) {
        $output .= '<p class="hero-subtitle">' . esc_html($subtitle) . '</p>';
    }
    $output .= '</div>';

    return $output;
}

/**
 * Registra os blocos Gutenberg do plugin (renderização server-side, sem Node.js).
 */
function um_dia_no_parque_register_blocks() {
    register_block_type(UM_DIA_NO_PARQUE_PLUGIN_DIR . 'build', array(
        'render_callback' => 'um_dia_no_parque_render_hero_block',
    ));
}
add_action('init', 'um_dia_no_parque_register_blocks');

function run_um_dia_no_parque() {
    $plugin = new Um_Dia_No_Parque();
}

run_um_dia_no_parque();
