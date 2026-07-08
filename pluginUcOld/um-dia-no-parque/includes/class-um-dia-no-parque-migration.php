<?php
/**
 * Migration: Fix UF-Cidade linkage.
 *
 * Repairs the following data corruption issues:
 * 1. Wrong UF siglas (substr instead of proper sigla).
 * 2. Duplicate UF posts (import + seed created extra copies).
 * 3. _cidade_uf term meta pointing to old (deleted) UF post IDs.
 * 4. Missing cidade terms (only ~2890 instead of ~5570+).
 *
 * @since      1.9.2
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration class.
 */
class Um_Dia_No_Parque_Migration {

    /**
     * Singleton.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Canonical sigla → full name mapping.
     */
    const ESTADOS = array(
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    );

    /**
     * Get singleton.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    private function __construct() {
        add_action('admin_init', array($this, 'maybe_run'), 5);
    }

    /**
     * Run migration if flag is not set.
     */
    public function maybe_run(): void {
        if (get_option('umdnp_cidades_relink_done', false)) {
            return;
        }
        $this->run();
        update_option('umdnp_cidades_relink_done', true);
    }

    /**
     * Main migration logic.
     */
    public function run(): void {
        $this->fix_uf_siglas();
        $this->create_missing_ufs();
        $this->deduplicate_ufs();
        $this->relink_cidade_terms();
        $this->reseed_missing_cidades();
    }

    // ============================================================
    // STEP 1: Fix UF siglas
    // ============================================================

    /**
     * Force-correct all UF siglas using the canonical title→sigla mapping.
     *
     * Normalizes titles to uppercase for comparison, then overwrites
     * the _uf_sigla meta regardless of current value.
     */
    private function fix_uf_siglas(): void {
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        $title_to_sigla = array();
        foreach (self::ESTADOS as $sigla => $nome) {
            $title_to_sigla[strtoupper(remove_accents($nome))] = $sigla;
            $title_to_sigla[strtoupper($nome)] = $sigla;
        }

        foreach ($ufs as $uf_id) {
            $title = strtoupper(get_the_title($uf_id));
            // Remove accents for comparison.
            $title_clean = strtr($title, array(
                'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
                'É' => 'E', 'Ê' => 'E',
                'Í' => 'I',
                'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                'Ú' => 'U',
                'Ç' => 'C',
            ));

            if (isset($title_to_sigla[$title_clean])) {
                $correct_sigla = $title_to_sigla[$title_clean];
                update_post_meta($uf_id, '_uf_sigla', $correct_sigla);
            }
        }
    }

    // ============================================================
    // STEP 1b: Create missing UF posts
    // ============================================================

    /**
     * Create UF posts for states that don't exist yet.
     *
     * Checks each canonical state by sigla meta; if no UF post has
     * that sigla, creates one with the proper title and sigla.
     */
    private function create_missing_ufs(): void {
        $existing_siglas = array();
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, '_uf_sigla', true);
            if (!empty($sigla)) {
                $existing_siglas[] = $sigla;
            }
        }

        foreach (self::ESTADOS as $sigla => $nome) {
            if (in_array($sigla, $existing_siglas, true)) {
                continue;
            }

            $post_id = wp_insert_post(array(
                'post_title'  => $nome,
                'post_type'   => 'uf',
                'post_status' => 'publish',
            ));

            if (!is_wp_error($post_id) && $post_id > 0) {
                update_post_meta($post_id, '_uf_sigla', $sigla);
            }
        }
    }

    // ============================================================
    // STEP 2: Deduplicate UF posts
    // ============================================================

    /**
     * Remove duplicate UF posts, keeping one per state.
     *
     * For each state, we keep the UF post with the correct sigla.
     * If duplicates still exist, keep the one with the highest ID
     * (most recently created is likely the seed's version which has
     * the rebuilt linkage).
     */
    private function deduplicate_ufs(): void {
        global $wpdb;

        // Get all UF posts grouped by sigla.
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        // Group by sigla.
        $by_sigla = array();
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, '_uf_sigla', true);
            if (empty($sigla)) {
                continue; // Skip entries without sigla (will be removed).
            }
            $by_sigla[$sigla][] = $uf_id;
        }

        // Also group by title for entries without sigla.
        $by_title = array();
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, '_uf_sigla', true);
            if (!empty($sigla)) {
                continue; // Already grouped by sigla.
            }
            $title = strtoupper(get_the_title($uf_id));
            $by_title[$title][] = $uf_id;
        }

        $to_delete = array();

        // Process sigla groups.
        foreach ($by_sigla as $sigla => $ids) {
            if (count($ids) <= 1) {
                continue;
            }
            // Sort by ID descending — keep the highest (most recent).
            rsort($ids);
            $keep = array_shift($ids);
            $to_delete = array_merge($to_delete, $ids);
        }

        // Process title-only groups (UF posts without sigla).
        foreach ($by_title as $title => $ids) {
            if (count($ids) <= 1) {
                continue;
            }
            rsort($ids);
            $keep = array_shift($ids);
            $to_delete = array_merge($to_delete, $ids);
        }

        // Also find orphans: UF posts without sigla that have a duplicate with sigla.
        foreach ($by_title as $title => $ids) {
            $title_clean = strtr($title, array(
                'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
                'É' => 'E', 'Ê' => 'E',
                'Í' => 'I',
                'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                'Ú' => 'U',
                'Ç' => 'C',
            ));
            foreach (self::ESTADOS as $sigla => $nome) {
                $nome_clean = strtoupper(strtr(remove_accents($nome), array(
                    'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A',
                    'É' => 'E', 'Ê' => 'E',
                    'Í' => 'I',
                    'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                    'Ú' => 'U',
                    'Ç' => 'C',
                )));
                if ($title_clean === $nome_clean) {
                    // This state has a UF without sigla. If there's already a UF
                    // with the correct sigla for this state, delete this one.
                    if (isset($by_sigla[$sigla]) && !empty($by_sigla[$sigla])) {
                        $to_delete = array_merge($to_delete, $ids);
                    }
                    break;
                }
            }
        }

        // Delete duplicates.
        foreach (array_unique($to_delete) as $del_id) {
            // Check if any cidade term references this UF via _cidade_uf.
            $terms = get_terms(array(
                'taxonomy'   => 'cidade',
                'fields'     => 'ids',
                'hide_empty' => false,
                'meta_query' => array(
                    array(
                        'key'   => '_cidade_uf',
                        'value' => (int) $del_id,
                        'type'  => 'NUMERIC',
                    ),
                ),
            ));

            $ucs_with_this_uf = array();
            if (!empty($terms) && !is_wp_error($terms)) {
                $uc_ids = get_objects_in_term($terms, 'cidade');
                $ucs_with_this_uf = !empty($uc_ids) ? array($uc_ids[0]) : array();
            }

            if (!empty($ucs_with_this_uf)) {
                // Migrate UC references to the kept UF.
                $keep_id = $this->find_kept_uf_for($del_id);
                if ($keep_id > 0) {
                    $this->migrate_uc_uf_references($del_id, $keep_id);
                }
            }

            wp_delete_post($del_id, true);
        }
    }

    /**
     * Find the UF post that should be kept for the same state as $old_uf_id.
     */
    private function find_kept_uf_for(int $old_uf_id): int {
        $sigla = get_post_meta($old_uf_id, '_uf_sigla', true);
        if (!empty($sigla)) {
            $ufs = get_posts(array(
                'post_type'      => 'uf',
                'posts_per_page' => 1,
                'meta_key'       => '_uf_sigla',
                'meta_value'     => $sigla,
                'exclude'        => array($old_uf_id),
                'fields'         => 'ids',
                'post_status'    => 'any',
            ));
            if (!empty($ufs)) {
                return (int) $ufs[0];
            }
        }
        return 0;
    }

    /**
     * Migrate cidade term references from old UF ID to new UF ID.
     */
    private function migrate_uc_uf_references(int $old_uf_id, int $new_uf_id): void {
        $terms = get_terms(array(
            'taxonomy'   => 'cidade',
            'fields'     => 'ids',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key'   => '_cidade_uf',
                    'value' => (int) $old_uf_id,
                    'type'  => 'NUMERIC',
                ),
            ),
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term_id) {
                update_term_meta($term_id, '_cidade_uf', $new_uf_id);
            }
        }
    }

    // ============================================================
    // STEP 3: Relink _cidade_uf term meta
    // ============================================================

    /**
     * Re-link all cidade terms' _cidade_uf to current valid UF posts.
     *
     * Uses cached IBGE data if available, otherwise re-fetches from API.
     * Overwrites ALL existing _cidade_uf values regardless of current value.
     */
    private function relink_cidade_terms(): void {
        global $wpdb;

        $data = $this->get_cidades_data();
        if (empty($data)) {
            return;
        }

        // Build sigla → UF ID map.
        $sigla_to_uf = array();
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, '_uf_sigla', true);
            if (!empty($sigla)) {
                $sigla_to_uf[$sigla] = $uf_id;
            }
        }

        $updated = 0;
        $not_found = 0;

        foreach ($data as $sigla => $cidades) {
            $uf_id = isset($sigla_to_uf[$sigla]) ? $sigla_to_uf[$sigla] : 0;
            if ($uf_id < 1) {
                continue;
            }

            foreach ($cidades as $cidade_nome) {
                // Find the term by name.
                $term = term_exists($cidade_nome, 'cidade');
                if ($term && isset($term['term_id'])) {
                    $term_id = (int) $term['term_id'];
                    update_term_meta($term_id, '_cidade_uf', $uf_id);
                    $updated++;
                } else {
                    $not_found++;
                }
            }
        }
    }

    /**
     * Get cidade data from IBGE API or cache.
     *
     * @return array  sigla => [city_name, ...]
     */
    private function get_cidades_data(): array {
        // Try transient cache first.
        $cache_key = 'umdnp_cidades_seed';
        $cached    = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        // Fetch from IBGE API.
        $data = array();
        foreach (self::ESTADOS as $sigla => $nome) {
            // Map sigla to IBGE code.
            $ibge = $this->sigla_to_ibge($sigla);
            if ($ibge < 1) {
                continue;
            }

            $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$ibge}/municipios";
            $response = wp_remote_get($url, array(
                'timeout'    => 30,
                'user-agent' => 'UmDiaNoParque/1.0',
            ));

            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                continue;
            }

            $body  = wp_remote_retrieve_body($response);
            $items = json_decode($body, true);

            if (!is_array($items) || empty($items)) {
                continue;
            }

            $cidades = array();
            foreach ($items as $item) {
                if (isset($item['nome'])) {
                    $cidades[] = $item['nome'];
                }
            }

            if (!empty($cidades)) {
                sort($cidades);
                $data[$sigla] = $cidades;
            }

            sleep(1); // Rate limit.
        }

        // Cache for 30 days.
        if (!empty($data)) {
            set_transient($cache_key, $data, 30 * DAY_IN_SECONDS);
        }

        return $data;
    }

    /**
     * Map sigla to IBGE code.
     */
    private function sigla_to_ibge(string $sigla): int {
        $map = array(
            'AC' => 12, 'AL' => 27, 'AP' => 16, 'AM' => 13,
            'BA' => 29, 'CE' => 23, 'DF' => 53, 'ES' => 32,
            'GO' => 52, 'MA' => 21, 'MT' => 51, 'MS' => 50,
            'MG' => 31, 'PA' => 15, 'PB' => 25, 'PR' => 41,
            'PE' => 26, 'PI' => 22, 'RJ' => 33, 'RN' => 24,
            'RS' => 43, 'RO' => 11, 'RR' => 14, 'SC' => 42,
            'SP' => 35, 'SE' => 28, 'TO' => 17,
        );
        return isset($map[$sigla]) ? $map[$sigla] : 0;
    }

    // ============================================================
    // STEP 4: Re-seed missing cidade terms
    // ============================================================

    /**
     * Create any missing cidade terms (IBGE has ~5570, we may have ~2890).
     */
    private function reseed_missing_cidades(): void {
        $data = $this->get_cidades_data();
        if (empty($data)) {
            return;
        }

        // Build sigla → UF ID map.
        $sigla_to_uf = array();
        $ufs = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));
        foreach ($ufs as $uf_id) {
            $sigla = get_post_meta($uf_id, '_uf_sigla', true);
            if (!empty($sigla)) {
                $sigla_to_uf[$sigla] = $uf_id;
            }
        }

        foreach ($data as $sigla => $cidades) {
            $uf_id = isset($sigla_to_uf[$sigla]) ? $sigla_to_uf[$sigla] : 0;
            if ($uf_id < 1) {
                continue;
            }

            foreach ($cidades as $cidade_nome) {
                $term = term_exists($cidade_nome, 'cidade');
                if ($term && isset($term['term_id'])) {
                    // Term exists — _cidade_uf already updated in Step 3.
                    continue;
                }

                // Create new term.
                $result = wp_insert_term($cidade_nome, 'cidade');
                if (is_wp_error($result) || !isset($result['term_id'])) {
                    continue;
                }
                $term_id = (int) $result['term_id'];
                update_term_meta($term_id, '_cidade_uf', $uf_id);
            }
        }
    }
}
