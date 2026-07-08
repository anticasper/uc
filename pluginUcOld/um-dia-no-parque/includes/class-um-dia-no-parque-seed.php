<?php
/**
 * Seed: Brazilian states and cities.
 *
 * On activation, creates all 27 UF CPT posts and all 5.570+ municipality
 * terms in the 'cidade' taxonomy, each linked to its UF via _cidade_uf.
 *
 * City data is fetched from the IBGE public API and cached locally.
 *
 * @since      1.9.1
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Seed class.
 */
/**
 * Seed class for Brazilian states and cities.
 *
 * @since 1.9.1
 */
class Um_Dia_No_Parque_Seed {

    /**
     * Singleton instance.
     *
     * @since  1.9.1
     * @var    self|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since  1.9.1
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone() {}

    /**
     * Prevent unserialize of the singleton.
     *
     * @throws \Exception Always throws.
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Constructor.
     */
    private function __construct() {}

    /**
     * All 27 Brazilian states with IBGE codes and siglas.
     */
    const ESTADOS = array(
        array('sigla' => 'AC', 'nome' => 'Acre', 'ibge' => 12),
        array('sigla' => 'AL', 'nome' => 'Alagoas', 'ibge' => 27),
        array('sigla' => 'AP', 'nome' => 'Amapá', 'ibge' => 16),
        array('sigla' => 'AM', 'nome' => 'Amazonas', 'ibge' => 13),
        array('sigla' => 'BA', 'nome' => 'Bahia', 'ibge' => 29),
        array('sigla' => 'CE', 'nome' => 'Ceará', 'ibge' => 23),
        array('sigla' => 'DF', 'nome' => 'Distrito Federal', 'ibge' => 53),
        array('sigla' => 'ES', 'nome' => 'Espírito Santo', 'ibge' => 32),
        array('sigla' => 'GO', 'nome' => 'Goiás', 'ibge' => 52),
        array('sigla' => 'MA', 'nome' => 'Maranhão', 'ibge' => 21),
        array('sigla' => 'MT', 'nome' => 'Mato Grosso', 'ibge' => 51),
        array('sigla' => 'MS', 'nome' => 'Mato Grosso do Sul', 'ibge' => 50),
        array('sigla' => 'MG', 'nome' => 'Minas Gerais', 'ibge' => 31),
        array('sigla' => 'PA', 'nome' => 'Pará', 'ibge' => 15),
        array('sigla' => 'PB', 'nome' => 'Paraíba', 'ibge' => 25),
        array('sigla' => 'PR', 'nome' => 'Paraná', 'ibge' => 41),
        array('sigla' => 'PE', 'nome' => 'Pernambuco', 'ibge' => 26),
        array('sigla' => 'PI', 'nome' => 'Piauí', 'ibge' => 22),
        array('sigla' => 'RJ', 'nome' => 'Rio de Janeiro', 'ibge' => 33),
        array('sigla' => 'RN', 'nome' => 'Rio Grande do Norte', 'ibge' => 24),
        array('sigla' => 'RS', 'nome' => 'Rio Grande do Sul', 'ibge' => 43),
        array('sigla' => 'RO', 'nome' => 'Rondônia', 'ibge' => 11),
        array('sigla' => 'RR', 'nome' => 'Roraima', 'ibge' => 14),
        array('sigla' => 'SC', 'nome' => 'Santa Catarina', 'ibge' => 42),
        array('sigla' => 'SP', 'nome' => 'São Paulo', 'ibge' => 35),
        array('sigla' => 'SE', 'nome' => 'Sergipe', 'ibge' => 28),
        array('sigla' => 'TO', 'nome' => 'Tocantins', 'ibge' => 17),
    );

    /**
     * Seed all UFs and cities.
     *
     * Idempotent: skips UFs/cities that already exist.
     */
    public function seed_all(): void {
        $this->seed_ufs();
        $this->seed_cidades();
    }

    /**
     * Ensure all 27 UF CPT posts exist.
     */
    public function seed_ufs(): void {
        foreach (self::ESTADOS as $estado) {
            $sigla = $estado['sigla'];
            $nome  = $estado['nome'];

            // Check if UF already exists by sigla meta.
            $existing = get_posts(array(
                'post_type'      => 'uf',
                'posts_per_page' => 1,
                'meta_key'       => Um_Dia_No_Parque_Meta::UF_SIGLA,
                'meta_value'     => $sigla,
                'fields'         => 'ids',
                'post_status'    => 'any',
            ));

            if (!empty($existing)) {
                continue;
            }

            // Check by title.
            $by_title = get_posts(array(
                'post_type'      => 'uf',
                'title'          => $nome,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => 'any',
            ));
            if (!empty($by_title)) {
                // Set sigla meta if missing.
                $existing_sigla = get_post_meta($by_title[0], Um_Dia_No_Parque_Meta::UF_SIGLA, true);
                if (empty($existing_sigla)) {
                    update_post_meta($by_title[0], Um_Dia_No_Parque_Meta::UF_SIGLA, $sigla);
                }
                continue;
            }

            // Create UF post.
            $uf_id = wp_insert_post(array(
                'post_type'   => 'uf',
                'post_title'  => $nome,
                'post_status' => 'publish',
            ), true);

            if (!is_wp_error($uf_id) && $uf_id > 0) {
                update_post_meta($uf_id, Um_Dia_No_Parque_Meta::UF_SIGLA, $sigla);
            }
        }
    }

    /**
     * Seed all cities from IBGE API or cache.
     *
     * For each UF, fetches municipalities from IBGE API, creates
     * 'cidade' terms with _cidade_uf term meta linking to the UF post.
     */
    public function seed_cidades(): void {
        // Try to load from cache first.
        $cache_key = 'umdnp_cidades_seed';
        $cached    = get_transient($cache_key);
        $data      = false;

        if (is_array($cached) && !empty($cached)) {
            $data = $cached;
        } else {
            // Fetch from IBGE API for each UF.
            $data = array();
            foreach (self::ESTADOS as $estado) {
                $ibge_id = $estado['ibge'];
                $sigla   = $estado['sigla'];

                $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$ibge_id}/municipios";
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

                // Rate limit: 1 request per second.
                sleep(1);
            }

            // Cache for 30 days.
            if (!empty($data)) {
                set_transient($cache_key, $data, 30 * DAY_IN_SECONDS);
            }
        }

        if (empty($data)) {
            return;
        }

        // Seed: all cities from IBGE API or cache.
        foreach ($data as $sigla => $cidades) {
            // Find UF post by sigla.
            $uf_posts = get_posts(array(
                'post_type'      => 'uf',
                'posts_per_page' => 1,
                'meta_key'       => Um_Dia_No_Parque_Meta::UF_SIGLA,
                'meta_value'     => $sigla,
                'fields'         => 'ids',
                'post_status'    => 'any',
            ));

            if (empty($uf_posts)) {
                continue;
            }
            $uf_id = (int) $uf_posts[0];

            foreach ($cidades as $cidade_nome) {
                $this->find_or_create_cidade($cidade_nome, $uf_id);
            }
        }
    }

    /**
     * Find or create a cidade term linked to a UF.
     *
     * @since  1.9.1
     * @param  string $nome  City name.
     * @param  int    $uf_id UF post ID.
     * @return int           Term ID, 0 on failure.
     */
    public function find_or_create_cidade(string $nome, int $uf_id): int {
        $term = term_exists($nome, 'cidade');
        if ($term && isset($term['term_id'])) {
            $term_id = (int) $term['term_id'];
        } else {
            $result = wp_insert_term($nome, 'cidade');
            if (is_wp_error($result) || !isset($result['term_id'])) {
                return 0;
            }
            $term_id = (int) $result['term_id'];
        }

        // Set UF link if not already set.
        if ($uf_id > 0) {
            $current_uf = get_term_meta($term_id, '_cidade_uf', true);
            if (empty($current_uf)) {
                update_term_meta($term_id, '_cidade_uf', $uf_id);
            }
        }

        return $term_id;
    }
}
