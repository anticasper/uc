<?php
/**
 * Test the AJAX endpoints — map data and cidade/UF resolution.
 *
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/tests
 */

/**
 * AJAX endpoint tests.
 */
class Test_AJAX_Endpoint extends WP_UnitTestCase {

    /**
     * UC post ID.
     *
     * @var int
     */
    protected $uc_id;

    /**
     * UF post ID.
     *
     * @var int
     */
    protected $uf_id;

    /**
     * Set up test fixtures.
     */
    public function set_up(): void {
        parent::set_up();

        // Create UF post.
        $uf_id = $this->factory()->post->create(array(
            'post_title'  => 'São Paulo',
            'post_type'   => 'uf',
            'post_status' => 'publish',
            'meta_input'  => array(
                '_uf_sigla' => 'SP',
            ),
        ));

        // Create cidade taxonomy term linked to this UF.
        $term = wp_insert_term('São Paulo', 'cidade');
        $this->assertNotWPError($term);
        $term_id = $term['term_id'];
        update_term_meta($term_id, '_cidade_uf', $uf_id);

        // Create a UC with real address fields.
        $uc_id = $this->factory()->post->create(array(
            'post_title'  => 'Parque do Ibirapuera',
            'post_type'   => 'uc',
            'post_status' => 'publish',
            'meta_input'  => array(
                '_uc_endereco'  => 'Av. Pedro Álvares Cabral',
                '_uc_numero'    => 's/n',
            ),
        ));

        // Link UC to cidade taxonomy.
        wp_set_object_terms($uc_id, array($term_id), 'cidade', true);

        // Pre-set geocode transient for the address so the test doesn't
        // require a real Nominatim HTTP call.
        $address = 'Av. Pedro Álvares Cabral, s/n, São Paulo, SP, Brasil';
        $cache_key = 'umdnp_geo_' . md5($address);
        set_transient($cache_key, array(
            'lat' => -23.5874,
            'lng' => -46.6576,
        ), DAY_IN_SECONDS);

        // Store IDs for test methods.
        $this->uc_id = $uc_id;
        $this->uf_id = $uf_id;
    }

    /**
     * Test that the map AJAX endpoint returns valid JSON.
     */
    public function test_map_data_endpoint(): void {
        $nonce = wp_create_nonce('um_dia_no_parque_elementor_nonce');

        $_GET = array(
            'action' => 'umdnp_get_parques_mapa',
            'nonce'  => $nonce,
            'search' => '',
            'tipo_atividade' => '',
            'regiao' => '',
            'estado' => '',
        );

        try {
            do_action('wp_ajax_umdnp_get_parques_mapa');
        } catch (WPDieException $e) {
            // wp_send_json_success calls wp_die(), which throws WPDieException.
        }

        $response = json_decode($e->getMessage(), true);

        $this->assertTrue($response['success'], 'Expected success: true');
        $this->assertArrayHasKey('markers', $response['data']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertEquals(1, $response['data']['total'], 'Expected 1 marker');
        $this->assertEquals('Parque do Ibirapuera', $response['data']['markers'][0]['name']);
        $this->assertEquals('São Paulo', $response['data']['markers'][0]['cidade']);
        $this->assertEquals('SP', $response['data']['markers'][0]['uf']);
    }

    /**
     * Test search filter by cidade taxonomy name.
     */
    public function test_map_data_search_by_cidade(): void {
        $nonce = wp_create_nonce('um_dia_no_parque_elementor_nonce');

        // Create a second UC in a different city with its own UF/cidade term.
        $uf_rj = $this->factory()->post->create(array(
            'post_title'  => 'Rio de Janeiro',
            'post_type'   => 'uf',
            'post_status' => 'publish',
            'meta_input'  => array(
                '_uf_sigla' => 'RJ',
            ),
        ));

        $term_rj = wp_insert_term('Rio de Janeiro', 'cidade');
        $this->assertNotWPError($term_rj);
        update_term_meta($term_rj['term_id'], '_cidade_uf', $uf_rj);

        $this->factory()->post->create(array(
            'post_title'  => 'Parque Nacional da Tijuca',
            'post_type'   => 'uc',
            'post_status' => 'publish',
            'meta_input'  => array(
                '_uc_endereco'  => 'Estrada da Cascatinha',
                '_uc_numero'    => '850',
            ),
        ));

        $rj_uc_id = $this->factory()->post->create();
        wp_set_object_terms($rj_uc_id, array($term_rj['term_id']), 'cidade', true);

        // Pre-set geocode transient for second UC.
        $address2 = 'Estrada da Cascatinha, 850, Rio de Janeiro, RJ, Brasil';
        $cache_key2 = 'umdnp_geo_' . md5($address2);
        set_transient($cache_key2, array(
            'lat' => -22.9519,
            'lng' => -43.2849,
        ), DAY_IN_SECONDS);

        // Search for "Ibirapuera" — should match title.
        $_GET = array(
            'action' => 'umdnp_get_parques_mapa',
            'nonce'  => $nonce,
            'search' => 'Ibirapuera',
            'tipo_atividade' => '',
            'regiao' => '',
            'estado' => '',
        );

        try {
            do_action('wp_ajax_umdnp_get_parques_mapa');
        } catch (WPDieException $e) {
            // Expected.
        }

        $response = json_decode($e->getMessage(), true);
        $this->assertEquals(1, $response['data']['total'], 'Expected 1 result for search');
        $this->assertEquals('Parque do Ibirapuera', $response['data']['markers'][0]['name']);
        $this->assertEquals('SP', $response['data']['markers'][0]['uf']);

        // Search by cidade name — should match via taxonomy.
        $_GET['search'] = 'Rio de Janeiro';
        try {
            do_action('wp_ajax_umdnp_get_parques_mapa');
        } catch (WPDieException $e) {
            // Expected.
        }
        $response = json_decode($e->getMessage(), true);
        $this->assertEquals(1, $response['data']['total'], 'Expected 1 result for cidade search');
        $this->assertEquals('Parque Nacional da Tijuca', $response['data']['markers'][0]['name']);
        $this->assertEquals('RJ', $response['data']['markers'][0]['uf']);
    }

    /**
     * Test filtering by cidade taxonomy.
     */
    public function test_map_data_filter_by_cidade(): void {
        $nonce = wp_create_nonce('um_dia_no_parque_elementor_nonce');

        $_GET = array(
            'action' => 'umdnp_get_parques_mapa',
            'nonce'  => $nonce,
            'search' => '',
            'cidade' => 'São Paulo',
            'tipo_atividade' => '',
            'regiao' => '',
            'estado' => '',
        );

        try {
            do_action('wp_ajax_umdnp_get_parques_mapa');
        } catch (WPDieException $e) {
            // Expected.
        }

        $response = json_decode($e->getMessage(), true);
        $this->assertEquals(1, $response['data']['total']);
        $this->assertEquals('Parque do Ibirapuera', $response['data']['markers'][0]['name']);
        $this->assertEquals('SP', $response['data']['markers'][0]['uf']);
    }
}
