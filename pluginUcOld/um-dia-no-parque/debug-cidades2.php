<?php
/**
 * Debug V2: Check _cidade_uf term meta values specifically,
 * check _uf_sigla post meta, and show relinking needed.
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

global $wpdb;

echo "=== _cidade_uf term meta values vs actual UF posts ===\n\n";

// Get unique _cidade_uf values
$cidade_uf_values = $wpdb->get_col(
    "SELECT DISTINCT meta_value FROM {$wpdb->termmeta} WHERE meta_key = '_cidade_uf' ORDER BY meta_value+0"
);

echo "Total unique _cidade_uf values: " . count($cidade_uf_values) . "\n";
echo "Values: " . implode(', ', $cidade_uf_values) . "\n\n";

// Check each value against UF posts
echo "--- Checking each _cidade_uf value against UF posts ---\n";
$missing = [];
foreach ($cidade_uf_values as $val) {
    $post = get_post((int)$val);
    if ($post && $post->post_type === 'uf') {
        $sigla = get_post_meta($post->ID, '_uf_sigla', true);
        echo "  _cidade_uf={$val} -> UF: {$post->post_title} (ID={$post->ID}) sigla='{$sigla}' ✓\n";
    } else {
        echo "  _cidade_uf={$val} -> MISSING (no UF post with this ID)\n";
        $missing[] = $val;
    }
}

echo "\n=== Current UF posts ===\n";
$ufs = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'uf' AND post_status = 'publish' ORDER BY post_title");
echo "Total UF posts: " . count($ufs) . "\n";
foreach ($ufs as $uf) {
    $sigla = get_post_meta($uf->ID, '_uf_sigla', true);
    echo "  UF ID={$uf->ID} title='{$uf->post_title}' sigla='{$sigla}'\n";
}

// Check for _uf_sigla meta
echo "\n=== _uf_sigla post meta analysis ===\n";
$with_sigla = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'uf' AND pm.meta_key = '_uf_sigla' AND pm.meta_value != ''");
$total_ufs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'uf' AND post_status = 'publish'");
echo "UF posts with non-empty _uf_sigla: {$with_sigla} / {$total_ufs}\n";

if (empty($missing)) {
    echo "\n✓ No missing UF references!\n";
} else {
    echo "\n✗ MISSING UF references: " . implode(', ', $missing) . "\n";
}

echo "\n=== General counts ===\n";
echo "Total 'cidade' terms: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'cidade'") . "\n";
echo "Total _cidade_uf meta entries: " . $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = '_cidade_uf'") . "\n";

echo "\n=== Done ===\n";
