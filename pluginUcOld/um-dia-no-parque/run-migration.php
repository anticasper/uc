<?php
/**
 * Run migration manually and report results.
 * Visit: http://umdianoparque.local/wp-content/plugins/um-dia-no-parque/run-migration.php
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

echo "<pre>\n";
echo "=== Iniciando migração manual ===\n\n";

// Load migration class if not already loaded.
if (!class_exists('Um_Dia_No_Parque_Migration')) {
    require_once __DIR__ . '/includes/class-um-dia-no-parque-migration.php';
}

$migration = Um_Dia_No_Parque_Migration::get_instance();

// Redefine run() to add logging — we just call the internal methods directly.
$ref = new ReflectionClass($migration);
$fix = $ref->getMethod('fix_uf_siglas');
$dedup = $ref->getMethod('deduplicate_ufs');
$relink = $ref->getMethod('relink_cidade_terms');
$reseed = $ref->getMethod('reseed_missing_cidades');

$fix->setAccessible(true);
$dedup->setAccessible(true);
$relink->setAccessible(true);
$reseed->setAccessible(true);

echo "STEP 1: Fix UF siglas...\n";
$fix->invoke($migration);
echo "  ✓ OK\n\n";

echo "STEP 2: Deduplicate UF posts...\n";
$dedup->invoke($migration);
echo "  ✓ OK\n\n";

echo "STEP 3: Relink cidade terms (IBGE)...\n";
$relink->invoke($migration);
echo "  ✓ OK\n\n";

echo "STEP 4: Re-seed missing cidades...\n";
$reseed->invoke($migration);
echo "  ✓ OK\n\n";

echo "=== Salvando flag de conclusão ===\n";
update_option('umdnp_cidades_relink_done', true);

echo "\n=== Verificação pós-migração ===\n\n";
global $wpdb;

// UF count.
$uf_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'uf' AND post_status = 'publish'");
echo "UF posts (publish): {$uf_count}\n";

// Cidade term count.
$cidade_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'cidade'");
echo "Cidade terms: {$cidade_count}\n";

// _cidade_uf meta count.
$meta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = '_cidade_uf'");
echo "_cidade_uf entries: {$meta_count}\n";

// Check if any _cidade_uf still points to non-existent UFs.
$bad_refs = $wpdb->get_results(
    "SELECT tm.term_id, tm.meta_value
     FROM {$wpdb->termmeta} tm
     LEFT JOIN {$wpdb->posts} p ON p.ID = tm.meta_value AND p.post_type = 'uf'
     WHERE tm.meta_key = '_cidade_uf'
     AND p.ID IS NULL
     LIMIT 10"
);
echo "\n_cidade_uf pointing to non-existent UF: " . count($bad_refs);
if (!empty($bad_refs)) {
    echo " (showing first 10):\n";
    foreach ($bad_refs as $bad) {
        echo "  term_id={$bad->term_id} -> meta_value={$bad->meta_value}\n";
    }
} else {
    echo " ✓ Todos linkados corretamente!\n";
}

// List UFs with siglas.
$ufs = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'uf' AND post_status = 'publish' ORDER BY post_title");
echo "\nUF list:\n";
foreach ($ufs as $uf) {
    $sigla = get_post_meta($uf->ID, '_uf_sigla', true);
    $count_cidades = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->termmeta} tm
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
         WHERE tm.meta_key = '_cidade_uf' AND tm.meta_value = %d AND tt.taxonomy = 'cidade'",
        $uf->ID
    ));
    echo "  {$uf->ID} | {$uf->post_title} | sigla={$sigla} | cidades={$count_cidades}\n";
}

echo "\n=== FIM ===\n";
echo "</pre>\n";
