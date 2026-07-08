<?php
/**
 * Debug script: check cidade taxonomy terms and _cidade_uf meta.
 * Run from wp-content/plugins/um-dia-no-parque/ via CLI:
 * php debug-cidades.php
 * 
 * Or access via browser: /wp-content/plugins/um-dia-no-parque/debug-cidades.php
 */

// Prevent direct browser access - comment this out to run via browser
// For CLI, just load WP
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

echo "=== Cidade Taxonomy Debug ===\n\n";

// 1. Check the taxonomy is registered
echo "Taxonomy 'cidade' registered: " . (taxonomy_exists('cidade') ? 'YES' : 'NO') . "\n";

// 2. Count terms
$terms = get_terms(array(
    'taxonomy'   => 'cidade',
    'hide_empty' => false,
    'fields'     => 'ids',
));
echo "Total 'cidade' terms: " . count($terms) . "\n";

if (count($terms) > 0) {
    // 3. Check _cidade_uf term meta
    global $wpdb;
    $with_meta = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s",
        '_cidade_uf'
    ));
    echo "Terms with _cidade_uf meta: {$with_meta}\n";
    
    // 4. Show a sample of terms WITHOUT _cidade_uf
    $without_meta = $wpdb->get_results($wpdb->prepare(
        "SELECT t.term_id, t.name 
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'cidade'
         AND t.term_id NOT IN (
             SELECT tm.term_id FROM {$wpdb->termmeta} tm WHERE tm.meta_key = %s
         )
         LIMIT 10",
        '_cidade_uf'
    ));
    
    if (!empty($without_meta)) {
        echo "\n--- Sample terms WITHOUT _cidade_uf meta ---\n";
        foreach ($without_meta as $t) {
            echo "  term_id={$t->term_id} name='{$t->name}'\n";
        }
        echo "Total without meta: " . count($without_meta) . " (showing up to 10)\n";
    } else {
        echo "ALL termos have _cidade_uf meta ✓\n";
    }
    
    // 5. Sample terms with _cidade_uf - verify range
    $with_meta_samples = $wpdb->get_results($wpdb->prepare(
        "SELECT tm.term_id, tm.meta_value, t.name
         FROM {$wpdb->termmeta} tm
         INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
         WHERE tm.meta_key = %s
         LIMIT 20",
        '_cidade_uf'
    ));
    
    echo "\n--- Sample terms WITH _cidade_uf ---\n";
    foreach ($with_meta_samples as $s) {
        // Check if the UF post exists
        $uf_post = get_post($s->meta_value);
        $uf_exists = $uf_post ? "{$uf_post->post_title} (ID={$uf_post->ID})" : 'MISSING';
        echo "  term_id={$s->term_id} name='{$s->name}' _cidade_uf={$s->meta_value} -> UF: {$uf_exists}\n";
    }

    // 6. Check UF posts
    $ufs = get_posts(array(
        'post_type' => 'uf',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'fields' => 'ids',
    ));
    echo "\nTotal UF posts: " . count($ufs) . "\n";
    if (!empty($ufs)) {
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, 'uf_sigla', true);
            echo "  UF ID={$uf_id} title='" . get_the_title($uf_id) . "' sigla='{$sigla}'\n";
        }
    }
}

echo "\n=== Done ===\n";
