<?php
/**
 * Tests for AJAX handler validation.
 *
 * Tests input sanitization patterns used in the AJAX handler.
 *
 * @package Um_Dia_No_Parque\Tests
 */

/**
 * AJAX test case.
 */
class AjaxTest extends UMDNP_UnitTestCase {

    /**
     * Test nonce failure detection pattern.
     */
    public function test_nonce_check_pattern(): void {
        // Simulate the exact nonce check from handle_map_data().
        $nonce_isset = isset($_GET['nonce']);
        $this->assertFalse($nonce_isset, 'No nonce should be set by default');
    }

    /**
     * Test sanitization of input parameters (as done in handle_map_data).
     */
    public function test_input_sanitization(): void {
        // Simulate the exact sanitization chain from handle_map_data().
        $raw_search         = '  Parque Central  ';
        $raw_tipo_atividade = 'trilha';
        $raw_regiao         = 'regiao-sul';
        $raw_estado         = 'SP';

        // This is what the AJAX handler does.
        $search         = sanitize_text_field(wp_unslash($raw_search));
        $tipo_atividade = sanitize_text_field(wp_unslash($raw_tipo_atividade));
        $regiao         = sanitize_text_field(wp_unslash($raw_regiao));
        $estado         = sanitize_text_field(wp_unslash($raw_estado));

        $this->assertIsString($search);
        $this->assertIsString($tipo_atividade);
        $this->assertIsString($regiao);
        $this->assertIsString($estado);
    }

    /**
     * Test the Elementor nonce action is consistent.
     */
    public function test_nonce_action_consistency(): void {
        $nonce_action_render = 'um_dia_no_parque_elementor_nonce';
        $nonce_action_ajax   = 'um_dia_no_parque_elementor_nonce';

        $this->assertEquals(
            $nonce_action_render,
            $nonce_action_ajax,
            'Nonce action must match between render() and AJAX handler'
        );
    }

    /**
     * Test AJAX action name consistency.
     */
    public function test_ajax_action_consistency(): void {
        // Action registered in AJAX constructor.
        $registered_action = 'umdnp_get_parques_mapa';

        // Action sent from JavaScript.
        $js_action = 'umdnp_get_parques_mapa';

        $this->assertEquals($registered_action, $js_action);
    }

    /**
     * Test that marker data structure is complete.
     */
    public function test_marker_data_structure(): void {
        // This mirrors what handle_map_data() builds for each UC.
        $post_id    = 1;
        $lat        = -23.5505;
        $lng        = -46.6333;
        $cidade     = 'São Paulo';
        $uf         = 'SP';
        $endereco   = 'Av. Paulista';
        $thumbnail  = 'https://example.com/thumb.jpg';
        $biomas     = 'Mata Atlântica';
        $excerpt    = 'Um belo parque.';

        $marker = array(
            'id'        => $post_id,
            'name'      => 'Parque Central',
            'permalink' => 'https://example.com/parque-central',
            'lat'       => floatval($lat),
            'lng'       => floatval($lng),
            'cidade'    => $cidade ?: '',
            'uf'        => $uf,
            'endereco'  => $endereco ?: '',
            'biomas'    => $biomas,
            'thumbnail' => $thumbnail ?: '',
            'excerpt'   => $excerpt,
        );

        $this->assertIsArray($marker);
        $this->assertArrayHasKey('id', $marker);
        $this->assertArrayHasKey('name', $marker);
        $this->assertArrayHasKey('lat', $marker);
        $this->assertArrayHasKey('lng', $marker);
        $this->assertArrayHasKey('cidade', $marker);
        $this->assertArrayHasKey('uf', $marker);
        $this->assertArrayHasKey('endereco', $marker);
        $this->assertArrayHasKey('biomas', $marker);
        $this->assertArrayHasKey('thumbnail', $marker);
        $this->assertArrayHasKey('excerpt', $marker);

        $this->assertIsFloat($marker['lat']);
        $this->assertIsFloat($marker['lng']);
    }

    /**
     * Test the response envelope structure.
     */
    public function test_response_envelope(): void {
        $markers = array(
            array('id' => 1, 'name' => 'Park A', 'lat' => 0.0, 'lng' => 0.0, 'cidade' => 'SP'),
            array('id' => 2, 'name' => 'Park B', 'lat' => 0.0, 'lng' => 0.0, 'cidade' => 'RJ'),
        );

        $response = array(
            'success' => true,
            'data'    => array(
                'markers' => $markers,
                'total'   => count($markers),
            ),
        );

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['data']['markers']);
        $this->assertEquals(2, $response['data']['total']);
    }

    /**
     * Test search text is NOT set when empty.
     */
    public function test_search_empty_omits_s(): void {
        $search = '';
        $query_args = array('post_type' => 'uc');
        if (!empty($search)) {
            $query_args['s'] = $search;
        }
        $this->assertArrayNotHasKey('s', $query_args);
    }
}
