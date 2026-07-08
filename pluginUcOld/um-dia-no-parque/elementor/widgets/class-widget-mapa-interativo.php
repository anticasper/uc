<?php
/**
 * E\Elementor Widget: Mapa Interativo de Parques
 *
 * Widget com filtros e mapa interativo (Leaflet.js + OpenStreetMap)
 * para visualizar todos os parques cadastrados.
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/e\Elementor/widgets
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mapa Interativo de Parques — E\Elementor Widget.
 *
 * @since 1.0.0
 */
class Um_Dia_No_Parque_Widget_Mapa_Interativo extends Um_Dia_No_Parque_Widget_Base {

    public function get_name() {
        return 'um-dia-no-parque-mapa-interativo';
    }

    public function get_title() {
        return esc_html__('Mapa Interativo de Parques', 'um-dia-no-parque');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_keywords() {
        return array(
            'parque', 'park', 'mapa', 'map', 'interativo', 'interactive',
            'leaflet', 'openstreetmap', 'um dia no parque',
            'localização', 'location', 'marcador', 'marker',
        );
    }

    public function get_style_depends() {
        return array_merge(parent::get_style_depends(), array('leaflet', 'leaflet-cluster', 'leaflet-cluster-default'));
    }

    public function get_script_depends() {
        return array_merge(parent::get_script_depends(), array('leaflet', 'leaflet-cluster'));
    }

    /**
     * Indicate this widget uses dynamic content (rendered via JS/AJAX).
     *
     * @since  1.0.0
     * @return bool
     */
    public function is_dynamic_content(): bool {
        return true;
    }

    /**
     * Register widget controls.
     *
     * @since 1.0.0
     */
    protected function register_controls() {

        // ================================================================
        // 1. CONTENT TAB
        // ================================================================

        // ----------------------------------------------------------------
        // 1.1 Content — Map Settings
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'map_section',
            array(
                'label' => esc_html__('Configuração do Mapa', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'map_heading',
            array(
                'label'     => esc_html__('Dimensões e Coordenadas', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_responsive_control(
            'map_height',
            array(
                'label'      => esc_html__('Altura do Mapa', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh', 'vw'),
                'range'      => array(
                    'px' => array(
                        'min'  => 200,
                        'max'  => 1200,
                    ),
                    'vh' => array(
                        'min'  => 20,
                        'max'  => 100,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 500,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-container' => 'height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'map_width',
            array(
                'label'      => esc_html__('Largura do Mapa', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%', 'vw'),
                'range'      => array(
                    'px' => array(
                        'min'  => 200,
                        'max'  => 1920,
                        'step' => 10,
                    ),
                    '%' => array(
                        'min'  => 20,
                        'max'  => 100,
                        'step' => 5,
                    ),
                    'vw' => array(
                        'min'  => 20,
                        'max'  => 100,
                        'step' => 5,
                    ),
                ),
                'default'    => array(
                    'unit' => '%',
                    'size' => '',
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-container' => 'width: {{SIZE}}{{UNIT}}; flex: none;',
                ),
                'description' => esc_html__('Largura do container do mapa. Deixe vazio para usar a largura padrão (flex).', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'default_lat',
            array(
                'label'       => esc_html__('Latitude Central (padrão)', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '-14.2350',
                'description' => esc_html__('Coordenada central do mapa quando nenhum parque é selecionado.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'default_lng',
            array(
                'label'       => esc_html__('Longitude Central (padrão)', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '-51.9253',
                'description' => esc_html__('Coordenada central do mapa quando nenhum parque é selecionado.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'default_zoom',
            array(
                'label'      => esc_html__('Zoom Inicial', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array(),
                'range'      => array(
                    'px' => array(
                        'min'  => 1,
                        'max'  => 18,
                        'step' => 1,
                    ),
                ),
                'default'    => array(
                    'size' => 4,
                ),
            )
        );

        $this->add_control(
            'map_hr_1',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        $this->add_control(
            'marker_behavior_heading',
            array(
                'label'     => esc_html__('Marcadores e Comportamento', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_control(
            'marker_clustering',
            array(
                'label'        => esc_html__('Agrupar Marcadores (Cluster)', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => esc_html__('Agrupa marcadores próximos em um único ícone com contagem.', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'cluster_radius',
            array(
                'label'     => esc_html__('Raio do Cluster (px)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::NUMBER,
                'default'   => 50,
                'min'       => 10,
                'max'       => 200,
                'step'      => 5,
                'condition' => array(
                    'marker_clustering' => 'yes',
                ),
            )
        );

        $this->add_control(
            'scroll_wheel_zoom',
            array(
                'label'        => esc_html__('Zoom com Scroll do Mouse', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Ativado', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Desativado', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'show_zoom_controls',
            array(
                'label'        => esc_html__('Botões de Zoom no Mapa', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Mostrar', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Ocultar', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'map_hr_2',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        $this->add_control(
            'tile_layer_heading',
            array(
                'label'     => esc_html__('Camada do Mapa (Tile Layer)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_control(
            'tile_layer_url',
            array(
                'label'       => esc_html__('URL do Tile Layer', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'description' => esc_html__('URL do servidor de tiles. Use {s}, {z}, {x}, {y} como placeholders.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'tile_layer_attribution',
            array(
                'label'       => esc_html__('Atribuição do Tile Layer', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
                'description' => esc_html__('Texto de atribuição exibido no mapa.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'tile_max_zoom',
            array(
                'label'   => esc_html__('Zoom Máximo do Tile', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 19,
                'min'     => 1,
                'max'     => 22,
                'step'    => 1,
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 1.3 Content — Sidebar List Settings
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'list_settings_section',
            array(
                'label' => esc_html__('Lista Lateral de Parques', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'show_parque_list',
            array(
                'label'        => esc_html__('Mostrar Lista Lateral', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => esc_html__('Exibe uma lista de parques ao lado do mapa.', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'list_position',
            array(
                'label'     => esc_html__('Posição da Lista', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::CHOOSE,
                'options'   => array(
                    'left'  => array(
                        'title' => esc_html__('Esquerda', 'um-dia-no-parque'),
                        'icon'  => 'eicon-h-align-left',
                    ),
                    'right' => array(
                        'title' => esc_html__('Direita', 'um-dia-no-parque'),
                        'icon'  => 'eicon-h-align-right',
                    ),
                ),
                'default'   => 'right',
                'condition' => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_responsive_control(
            'list_width',
            array(
                'label'      => esc_html__('Largura da Lista', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range'      => array(
                    'px' => array(
                        'min'  => 150,
                        'max'  => 600,
                    ),
                    '%'  => array(
                        'min' => 15,
                        'max' => 50,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 320,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-com-lista .umdnp-mapa-lista' => 'width: {{SIZE}}{{UNIT}};',
                ),
                'condition'  => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_max_height',
            array(
                'label'      => esc_html__('Altura Máxima da Lista', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'vh'),
                'range'      => array(
                    'px' => array(
                        'min'  => 200,
                        'max'  => 1200,
                    ),
                    'vh' => array(
                        'min'  => 30,
                        'max'  => 100,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 500,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-itens' => 'max-height: {{SIZE}}{{UNIT}};',
                ),
                'condition'  => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_show_thumbnail',
            array(
                'label'        => esc_html__('Mostrar Miniatura nos Itens', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_show_status',
            array(
                'label'        => esc_html__('Mostrar Status nos Itens', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_show_city',
            array(
                'label'        => esc_html__('Mostrar Cidade nos Itens', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_show_uf',
            array(
                'label'        => esc_html__('Mostrar Estado (UF) nos Itens', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_show_endereco',
            array(
                'label'        => esc_html__('Mostrar Endereço nos Itens', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
                'condition'    => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 1.4 Content — Marker & Popup Settings
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'marker_content_section',
            array(
                'label' => esc_html__('Popup dos Marcadores', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'popup_show_image',
            array(
                'label'        => esc_html__('Mostrar Imagem no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_default_image',
            array(
                'label'       => esc_html__('Imagem Padrão (fallback)', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::MEDIA,
                'description' => esc_html__('Usada quando uma UC não possui imagem destacada. Aparece no popup do marcador e na lista lateral.', 'um-dia-no-parque'),
                'label_block' => true,
                'condition'   => array(
                    'popup_show_image' => 'yes',
                ),
            )
        );

        $this->add_control(
            'popup_show_status',
            array(
                'label'        => esc_html__('Mostrar Status no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_show_city',
            array(
                'label'        => esc_html__('Mostrar Cidade no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_show_uf',
            array(
                'label'        => esc_html__('Mostrar Estado (UF) no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_show_endereco',
            array(
                'label'        => esc_html__('Mostrar Endereço no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_show_cep',
            array(
                'label'        => esc_html__('Mostrar CEP no Popup', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'popup_extra_link_text',
            array(
                'label'       => esc_html__('Texto do Link "Ver Detalhes"', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Ver detalhes', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 1.5 Content — Loading & Empty States
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'states_section',
            array(
                'label' => esc_html__('Estados Loading e Vazio', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'loading_text',
            array(
                'label'       => esc_html__('Texto de Carregamento', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Carregando mapa...', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'empty_text',
            array(
                'label'       => esc_html__('Texto sem Resultados', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Nenhum parque encontrado.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'initial_text',
            array(
                'label'       => esc_html__('Texto Inicial (antes da busca)', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'default'     => esc_html__('Selecione filtros para buscar parques.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 2. STYLE TAB
        // ================================================================

        // ----------------------------------------------------------------
        // 2.1 Style — Filters Container
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'filters_style_section',
            array(
                'label' => esc_html__('Filtros', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name'     => 'filters_background',
                'label'    => esc_html__('Fundo dos Filtros', 'um-dia-no-parque'),
                'types'    => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-filtros',
                'default'  => array(
                    'background' => 'classic',
                    'color'      => '#f9f9f9',
                ),
            )
        );

        $this->add_responsive_control(
            'filters_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtros' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
                'default'    => array(
                    'top'    => 15,
                    'right'  => 15,
                    'bottom' => 15,
                    'left'   => 15,
                    'unit'   => 'px',
                ),
            )
        );

        $this->add_responsive_control(
            'filters_margin',
            array(
                'label'      => esc_html__('Margem', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtros' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'filters_border',
                'label'    => esc_html__('Borda dos Filtros', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-filtros',
            )
        );

        $this->add_responsive_control(
            'filters_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtros' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
                'default'    => array(
                    'top'    => 8,
                    'right'  => 8,
                    'bottom' => 8,
                    'left'   => 8,
                    'unit'   => 'px',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'filters_box_shadow',
                'label'    => esc_html__('Sombra dos Filtros', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-filtros',
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.2 Style — Search Input
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'search_input_style_section',
            array(
                'label'     => esc_html__('Campo de Busca', 'um-dia-no-parque'),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'search_input_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_input_placeholder_color',
            array(
                'label'     => esc_html__('Cor do Placeholder', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input::placeholder' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_input_bg_color',
            array(
                'label'     => esc_html__('Cor de Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'search_input_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-busca-input',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'search_input_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-busca-input',
            )
        );

        $this->add_responsive_control(
            'search_input_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'search_input_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'search_input_height',
            array(
                'label'      => esc_html__('Altura do Input', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 30,
                        'max'  => 80,
                        'step' => 2,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => '',
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-busca-input' => 'height: {{SIZE}}{{UNIT}};',
                ),
                'description' => esc_html__('Ajuste a altura total do campo de busca.', 'um-dia-no-parque'),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.3 Style — Filter Selects
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'filter_selects_style_section',
            array(
                'label' => esc_html__('Filtros (Selects)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'filter_select_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-select' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'filter_select_bg_color',
            array(
                'label'     => esc_html__('Cor de Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-select' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'filter_select_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-filtro-select',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'filter_select_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-filtro-select',
            )
        );

        $this->add_responsive_control(
            'filter_select_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'filter_select_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'filter_select_height',
            array(
                'label'      => esc_html__('Altura do Select', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 30,
                        'max'  => 80,
                        'step' => 2,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => '',
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-select' => 'height: {{SIZE}}{{UNIT}};',
                ),
                'description' => esc_html__('Ajuste a altura total dos selects de filtro.', 'um-dia-no-parque'),
            )
        );

        $this->add_responsive_control(
            'filter_tipo_atividade_width',
            array(
                'label'      => esc_html__('Largura do Filtro Tipo de Atividade', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range'      => array(
                    'px' => array(
                        'min'  => 100,
                        'max'  => 600,
                        'step' => 10,
                    ),
                    '%' => array(
                        'min'  => 10,
                        'max'  => 100,
                        'step' => 5,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => '',
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtro-tipo-atividade' => 'width: {{SIZE}}{{UNIT}}; flex: none;',
                ),
                'description' => esc_html__('Largura do campo de seleção de tipo de atividade.', 'um-dia-no-parque'),
            )
        );

        $this->add_responsive_control(
            'filter_items_gap',
            array(
                'label'      => esc_html__('Espaçamento entre Filtros', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', 'em'),
                'range'      => array(
                    'px' => array(
                        'min'  => 0,
                        'max'  => 50,
                    ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-filtros-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.4 Style — Map Container
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'map_style_section',
            array(
                'label' => esc_html__('Mapa', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name'     => 'map_background',
                'label'    => esc_html__('Fundo do Mapa', 'um-dia-no-parque'),
                'types'    => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-container',
                'description' => esc_html__('Apenas visível enquanto o mapa carrega.', 'um-dia-no-parque'),
            )
        );

        $this->add_responsive_control(
            'map_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .umdnp-mapa-container .leaflet-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
                'default'    => array(
                    'top'    => 8,
                    'right'  => 8,
                    'bottom' => 8,
                    'left'   => 8,
                    'unit'   => 'px',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'map_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-container',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'map_box_shadow',
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-container',
            )
        );

        $this->add_responsive_control(
            'map_margin',
            array(
                'label'      => esc_html__('Margem', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-container' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.5 Style — Sidebar List Container
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'list_style_section',
            array(
                'label'     => esc_html__('Lista de Parques', 'um-dia-no-parque'),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array(
                    'show_parque_list' => 'yes',
                ),
            )
        );

        $this->add_control(
            'list_heading',
            array(
                'label'     => esc_html__('Container da Lista', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            array(
                'name'     => 'list_background',
                'label'    => esc_html__('Fundo da Lista', 'um-dia-no-parque'),
                'types'    => array('classic', 'gradient'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'list_border',
                'label'    => esc_html__('Borda da Lista', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista',
            )
        );

        $this->add_responsive_control(
            'list_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'list_box_shadow',
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista',
            )
        );

        $this->add_responsive_control(
            'list_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'list_header_hr',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        $this->add_control(
            'list_header_heading',
            array(
                'label'     => esc_html__('Cabeçalho', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_control(
            'list_header_bg_color',
            array(
                'label'     => esc_html__('Fundo do Cabeçalho', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-header' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'list_header_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-header' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'list_header_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista-header',
            )
        );

        $this->add_responsive_control(
            'list_header_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'list_item_hr',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        $this->add_control(
            'list_item_heading',
            array(
                'label'     => esc_html__('Itens da Lista', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_control(
            'list_item_bg_color',
            array(
                'label'     => esc_html__('Fundo do Item', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'list_item_hover_bg',
            array(
                'label'     => esc_html__('Fundo ao Passar Mouse', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'list_item_active_bg',
            array(
                'label'     => esc_html__('Fundo do Item Ativo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item.active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'list_item_name_color',
            array(
                'label'     => esc_html__('Cor do Nome', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item-name' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'list_item_name_typography',
                'label'    => esc_html__('Tipografia do Nome', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista-item-name',
            )
        );

        $this->add_control(
            'list_item_meta_color',
            array(
                'label'     => esc_html__('Cor dos Metadados (cidade, status)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item-cidade' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .umdnp-mapa-lista-item-status' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'list_item_meta_typography',
                'label'    => esc_html__('Tipografia dos Metadados', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista-item-cidade, {{WRAPPER}} .umdnp-mapa-lista-item-status',
            )
        );

        $this->add_control(
            'list_item_border_color',
            array(
                'label'     => esc_html__('Cor da Borda Inferior', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item' => 'border-bottom-color: {{VALUE}};',
                ),
                'default'   => '#e0e0e0',
            )
        );

        $this->add_responsive_control(
            'list_item_border_width',
            array(
                'label'      => esc_html__('Espessura da Borda Inferior', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 0,
                        'max'  => 10,
                    ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item' => 'border-bottom-width: {{SIZE}}px;',
                ),
            )
        );

        $this->add_responsive_control(
            'list_item_padding',
            array(
                'label'      => esc_html__('Padding do Item', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'list_item_spacing',
            array(
                'label'      => esc_html__('Espaçamento entre Itens', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 0,
                        'max'  => 30,
                    ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item' => 'margin-bottom: {{SIZE}}px;',
                ),
            )
        );

        $this->add_control(
            'list_thumbnail_heading',
            array(
                'label'     => esc_html__('Miniatura', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => array(
                    'list_show_thumbnail' => 'yes',
                ),
            )
        );

        $this->add_responsive_control(
            'list_thumbnail_size',
            array(
                'label'      => esc_html__('Tamanho da Miniaturas', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 30,
                        'max'  => 150,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 60,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-item-thumb img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .umdnp-mapa-lista-item-thumb' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
                'condition'  => array(
                    'list_show_thumbnail' => 'yes',
                ),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.6 Style — Marker Popup
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'popup_style_section',
            array(
                'label' => esc_html__('Popup dos Marcadores', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'popup_bg_color',
            array(
                'label'     => esc_html__('Fundo do Popup', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-wrapper' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'popup_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-popup-wrapper',
            )
        );

        $this->add_responsive_control(
            'popup_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-popup-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'popup_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-popup-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'popup_title_color',
            array(
                'label'     => esc_html__('Cor do Título', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-wrapper h4' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'popup_title_typography',
                'label'    => esc_html__('Tipografia do Título', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-popup-wrapper h4',
            )
        );

        $this->add_control(
            'popup_cidade_color',
            array(
                'label'     => esc_html__('Cor da Cidade', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-cidade' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'popup_cidade_typography',
                'label'    => esc_html__('Tipografia da Cidade', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-popup-cidade',
            )
        );

        $this->add_control(
            'popup_status_color',
            array(
                'label'     => esc_html__('Cor do Status', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-status' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'popup_link_color',
            array(
                'label'     => esc_html__('Cor do Link', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-link' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'popup_link_hover_color',
            array(
                'label'     => esc_html__('Cor do Link (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-popup-link:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'popup_link_typography',
                'label'    => esc_html__('Tipografia do Link', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-popup-link',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'popup_box_shadow',
                'label'    => esc_html__('Sombra do Popup', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-popup-wrapper',
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.7 Style — Marker Icons
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'markers_style_section',
            array(
                'label' => esc_html__('Marcadores (Ícones)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'marker_colors_heading',
            array(
                'label'     => esc_html__('Cores por Status', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_control(
            'marker_color_active',
            array(
                'label'     => esc_html__('Cor do Marcador (Aberto)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#4CAF50',
            )
        );

        $this->add_control(
            'marker_color_closed',
            array(
                'label'     => esc_html__('Cor do Marcador (Fechado)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#F44336',
            )
        );

        $this->add_control(
            'marker_color_maintenance',
            array(
                'label'     => esc_html__('Cor do Marcador (Manutenção/Reforma)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#FF9800',
            )
        );

        $this->add_control(
            'marker_icon_hr',
            array(
                'type' => \Elementor\Controls_Manager::DIVIDER,
            )
        );

        $this->add_control(
            'marker_icon_heading',
            array(
                'label'     => esc_html__('Tamanho e Estilo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'none',
            )
        );

        $this->add_responsive_control(
            'marker_size',
            array(
                'label'      => esc_html__('Tamanho do Marcador (px)', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 20,
                        'max'  => 60,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 32,
                ),
            )
        );

        $this->add_responsive_control(
            'marker_icon_size',
            array(
                'label'      => esc_html__('Tamanho do Ícone Interno', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array(
                        'min'  => 10,
                        'max'  => 40,
                    ),
                ),
                'default'    => array(
                    'unit' => 'px',
                    'size' => 18,
                ),
            )
        );

        $this->add_control(
            'marker_icon_source',
            array(
                'label'   => esc_html__('Fonte do Ícone', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'dashicon',
                'options' => array(
                    'dashicon'     => esc_html__('Ícone Dashicon', 'um-dia-no-parque'),
                    'custom_image' => esc_html__('Imagem Personalizada', 'um-dia-no-parque'),
                ),
            )
        );

        $this->add_control(
            'marker_icon_type',
            array(
                'label'   => esc_html__('Ícone do Marcador', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'dashicons-location',
                'options' => array(
                    'dashicons-location'     => esc_html__('Pin (Location)', 'um-dia-no-parque'),
                    'dashicons-location-alt' => esc_html__('Pin Alternativo', 'um-dia-no-parque'),
                    'dashicons-palmtree'     => esc_html__('Palmeira', 'um-dia-no-parque'),
                    'dashicons-leaf'         => esc_html__('Folha', 'um-dia-no-parque'),
                    'dashicons-star-filled'  => esc_html__('Estrela', 'um-dia-no-parque'),
                    'dashicons-heart'        => esc_html__('Coração', 'um-dia-no-parque'),
                    'dashicons-marker'       => esc_html__('Marcador Circular', 'um-dia-no-parque'),
                    'dashicons-flag'         => esc_html__('Bandeira', 'um-dia-no-parque'),
                    'dashicons-shield'       => esc_html__('Escudo', 'um-dia-no-parque'),
                    'dashicons-category'     => esc_html__('Categoria', 'um-dia-no-parque'),
                    'dashicons-pin'          => esc_html__('Alfinete', 'um-dia-no-parque'),
                ),
                'condition' => array(
                    'marker_icon_source' => 'dashicon',
                ),
            )
        );

        $this->add_control(
            'marker_custom_icon',
            array(
                'label'       => esc_html__('Imagem do Marcador', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::MEDIA,
                'description' => esc_html__('Faça upload de uma imagem PNG/SVG para usar como marcador no mapa.', 'um-dia-no-parque'),
                'label_block' => true,
                'condition'   => array(
                    'marker_icon_source' => 'custom_image',
                ),
            )
        );

        $this->add_control(
            'marker_icon_color',
            array(
                'label'     => esc_html__('Cor do Ícone Interno', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-marker-icon .dashicons' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'marker_border',
                'label'    => esc_html__('Borda do Marcador', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-marker-icon',
            )
        );

        $this->add_responsive_control(
            'marker_border_radius',
            array(
                'label'      => esc_html__('Border Radius', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-marker-icon' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'marker_shadow',
                'label'    => esc_html__('Sombra do Marcador', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-marker-icon',
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.8 Style — Loading Spinner
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'loading_style_section',
            array(
                'label' => esc_html__('Loading / Carregamento', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'loading_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-loading' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'loading_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-loading',
            )
        );

        $this->add_control(
            'loading_bg_color',
            array(
                'label'     => esc_html__('Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-loading' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'loading_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-loading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ----------------------------------------------------------------
        // 2.9 Style — Empty State
        // ----------------------------------------------------------------
        $this->start_controls_section(
            'empty_style_section',
            array(
                'label' => esc_html__('Estado Vazio (Sem Resultados)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'empty_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-placeholder' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'empty_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-mapa-lista-placeholder',
            )
        );

        $this->add_responsive_control(
            'empty_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-mapa-lista-placeholder' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render the filter controls HTML.
     *
     * Always renders all filters: search input, parque, bioma, cidade,
     * tipo de atividade selects, and Buscar button.
     *
     * @since  1.9.0
     * @return void
     */
    private function render_filters() {
        global $wpdb;
        ?>
        <div class="umdnp-mapa-filtros">
            <div class="umdnp-mapa-filtros-grid">

                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-busca">
                        <input type="text"
                               class="umdnp-mapa-filtro-input umdnp-mapa-busca-input"
                               placeholder="<?php echo esc_attr__('Buscar por CEP, parque, cidade ou unidade...', 'um-dia-no-parque'); ?>"
                               aria-label="<?php echo esc_attr__('Buscar parques', 'um-dia-no-parque'); ?>">
                    </div>

                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-parque">
                        <select class="umdnp-mapa-filtro-select" aria-label="<?php echo esc_attr__('Filtrar por parque', 'um-dia-no-parque'); ?>">
                            <option value=""><?php echo esc_html__('Parques', 'um-dia-no-parque'); ?></option>
                            <?php
                            $parques = get_posts(array(
                                'post_type'      => 'uc',
                                'posts_per_page' => -1,
                                'post_status'    => 'publish',
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                                'fields'         => 'ids',
                                'no_found_rows'  => true,
                            ));
                            foreach ($parques as $parque_id) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr($parque_id),
                                    esc_html(get_the_title($parque_id))
                                );
                            }
                            ?>
                        </select>
                    </div>

                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-unidade">
                        <select class="umdnp-mapa-filtro-select" aria-label="<?php echo esc_attr__('Filtrar por bioma', 'um-dia-no-parque'); ?>">
                            <option value=""><?php echo esc_html__('Biomas', 'um-dia-no-parque'); ?></option>
                            <?php
                            $biomas = get_terms(array(
                                'taxonomy'   => 'bioma',
                                'hide_empty' => true,
                            ));
                            if (!empty($biomas) && !is_wp_error($biomas)) {
                                foreach ($biomas as $bioma) {
                                    printf(
                                        '<option value="%s">%s</option>',
                                        esc_attr($bioma->slug),
                                        esc_html($bioma->name)
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-cidade">
                        <?php
                        $cidade_terms = get_terms(array(
                            'taxonomy'   => 'cidade',
                            'hide_empty' => false,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ));
                        $has_cidades = !empty($cidade_terms) && !is_wp_error($cidade_terms);
                        ?>
                        <select class="umdnp-mapa-filtro-select" aria-label="<?php echo esc_attr__('Filtrar por cidade', 'um-dia-no-parque'); ?>">
                            <?php if ($has_cidades) : ?>
                            <option value=""><?php echo esc_html__('Cidades', 'um-dia-no-parque'); ?></option>
                            <?php foreach ($cidade_terms as $term) : ?>
                                <option value="<?php echo esc_attr($term->name); ?>"><?php echo esc_html($term->name); ?></option>
                            <?php endforeach; ?>
                            <?php else : ?>
                            <option value=""><?php echo esc_html__('Nenhuma cidade disponível', 'um-dia-no-parque'); ?></option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$has_cidades && current_user_can('manage_options')) : ?>
                            <p class="umdnp-mapa-filtro-aviso" style="font-size:12px;color:#d63638;margin:4px 0 0;">
                                <?php
                                printf(
                                    /* translators: %s: URL to admin settings page */
                                    esc_html__('Cidades não carregadas. %s', 'um-dia-no-parque'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=um-dia-no-parque-settings&tab=ferramentas')) . '" target="_blank">' .
                                    esc_html__('Clique aqui para atualizar', 'um-dia-no-parque') . '</a>'
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-tipo-atividade">
                        <select class="umdnp-mapa-filtro-select" aria-label="<?php echo esc_attr__('Filtrar por tipo de atividade', 'um-dia-no-parque'); ?>">
                            <option value=""><?php echo esc_html__('Tipos de Atividade', 'um-dia-no-parque'); ?></option>
                            <?php
                            $tipos_atividade = get_terms(array(
                                'taxonomy'   => 'tipo_atividade',
                                'hide_empty' => true,
                            ));
                            if (!empty($tipos_atividade) && !is_wp_error($tipos_atividade)) {
                                foreach ($tipos_atividade as $tipo) {
                                    printf(
                                        '<option value="%s">%s</option>',
                                        esc_attr($tipo->slug),
                                        esc_html($tipo->name)
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>

            <div class="umdnp-mapa-filtros-actions">
                <button type="button" class="umdnp-mapa-btn-buscar">
                    <?php echo esc_html__('Buscar', 'um-dia-no-parque'); ?>
                </button>
            </div>

            </div>

        </div>
        <?php
    }

    /**
     * Render widget output on the frontend.
     *
     * @since 1.0.0
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Unique ID for this map instance.
        $map_id = 'umdnp-mapa-' . $this->get_id();
        $json_id = 'umdnp-config-' . $this->get_id();

        // Map settings.
        $map_height_unit = $settings['map_height']['unit'] ?? 'px';
        $map_height_size = !empty($settings['map_height']['size']) ? $settings['map_height']['size'] : 500;

        $default_lat  = !empty($settings['default_lat']) ? floatval($settings['default_lat']) : -14.2350;
        $default_lng  = !empty($settings['default_lng']) ? floatval($settings['default_lng']) : -51.9253;
        $default_zoom = !empty($settings['default_zoom']['size']) ? absint($settings['default_zoom']['size']) : 4;

        $marker_clustering = ('yes' === $settings['marker_clustering']);
        $cluster_radius    = !empty($settings['cluster_radius']) ? absint($settings['cluster_radius']) : 50;
        $scroll_wheel      = ('yes' === $settings['scroll_wheel_zoom']);
        $zoom_controls     = ('yes' === $settings['show_zoom_controls']);

        $tile_url        = !empty($settings['tile_layer_url']) ? $settings['tile_layer_url'] : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        $tile_attribution = !empty($settings['tile_layer_attribution']) ? $settings['tile_layer_attribution'] : '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>';
        $tile_max_zoom   = !empty($settings['tile_max_zoom']) ? absint($settings['tile_max_zoom']) : 19;

        // List settings.
        $show_parque_list = ('yes' === $settings['show_parque_list']);
        $list_position    = !empty($settings['list_position']) ? $settings['list_position'] : 'right';

        // Popup settings.
        $popup_show_image  = ('yes' === ($settings['popup_show_image'] ?? 'yes'));
        $popup_show_status = ('yes' === ($settings['popup_show_status'] ?? 'yes'));
        $popup_show_city   = ('yes' === ($settings['popup_show_city'] ?? 'yes'));
        $popup_show_uf     = ('yes' === ($settings['popup_show_uf'] ?? 'yes'));
        $popup_show_endereco = ('yes' === ($settings['popup_show_endereco'] ?? 'yes'));
        $popup_show_cep    = ('yes' === ($settings['popup_show_cep'] ?? 'yes'));
        $popup_link_text   = !empty($settings['popup_extra_link_text']) ? $settings['popup_extra_link_text'] : esc_html__('Ver detalhes', 'um-dia-no-parque');

        // List item display flags.
        $list_show_thumbnail = ('yes' === ($settings['list_show_thumbnail'] ?? 'yes'));
        $list_show_status    = ('yes' === ($settings['list_show_status'] ?? 'yes'));
        $list_show_city      = ('yes' === ($settings['list_show_city'] ?? 'yes'));
        $list_show_uf        = ('yes' === ($settings['list_show_uf'] ?? 'yes'));
        $list_show_endereco  = ('yes' === ($settings['list_show_endereco'] ?? 'yes'));

        // Texts.
        $loading_text       = !empty($settings['loading_text']) ? $settings['loading_text'] : esc_html__('Carregando mapa...', 'um-dia-no-parque');
        $empty_text         = !empty($settings['empty_text']) ? $settings['empty_text'] : esc_html__('Nenhum parque encontrado.', 'um-dia-no-parque');
        $initial_text       = !empty($settings['initial_text']) ? $settings['initial_text'] : esc_html__('Selecione filtros para buscar parques.', 'um-dia-no-parque');

        // Data attributes for JS.
        $map_data = array(
            'map_id'             => $map_id,
            'default_lat'        => $default_lat,
            'default_lng'        => $default_lng,
            'default_zoom'       => $default_zoom,
            'marker_clustering'  => $marker_clustering ? '1' : '0',
            'cluster_radius'     => $cluster_radius,
            'scroll_wheel_zoom'  => $scroll_wheel ? '1' : '0',
            'zoom_controls'      => $zoom_controls ? '1' : '0',
            'tile_url'           => $tile_url,
            'tile_attribution'   => html_entity_decode($tile_attribution, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'tile_max_zoom'      => $tile_max_zoom,
            'marker_size'        => !empty($settings['marker_size']['size']) ? absint($settings['marker_size']['size']) : 32,
            'marker_icon_size'   => !empty($settings['marker_icon_size']['size']) ? absint($settings['marker_icon_size']['size']) : 18,
            'marker_icon_source' => !empty($settings['marker_icon_source']) ? $settings['marker_icon_source'] : 'dashicon',
            'marker_icon_type'   => !empty($settings['marker_icon_type']) ? $settings['marker_icon_type'] : 'dashicons-location',
            'marker_custom_icon' => !empty($settings['marker_custom_icon']['url']) ? esc_url($settings['marker_custom_icon']['url']) : '',
            'marker_color_active'       => $settings['marker_color_active'] ?? '#4CAF50',
            'marker_color_closed'       => $settings['marker_color_closed'] ?? '#F44336',
            'marker_color_maintenance'   => $settings['marker_color_maintenance'] ?? '#FF9800',
            'popup_show_image'  => $popup_show_image ? '1' : '0',
            'popup_default_image' => !empty($settings['popup_default_image']['url']) ? esc_url($settings['popup_default_image']['url']) : '',
            'popup_show_status' => $popup_show_status ? '1' : '0',
            'popup_show_city'   => $popup_show_city ? '1' : '0',
            'popup_show_uf'     => $popup_show_uf ? '1' : '0',
            'popup_show_endereco' => $popup_show_endereco ? '1' : '0',
            'popup_show_cep'    => $popup_show_cep ? '1' : '0',
            'popup_link_text'   => $popup_link_text,
            'list_show_thumbnail' => $list_show_thumbnail ? '1' : '0',
            'list_show_status'    => $list_show_status ? '1' : '0',
            'list_show_city'      => $list_show_city ? '1' : '0',
            'list_show_uf'        => $list_show_uf ? '1' : '0',
            'list_show_endereco'  => $list_show_endereco ? '1' : '0',
            'ajax_url'         => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('um_dia_no_parque_elementor_nonce'),
            'i18n'             => array(
                'loading'    => esc_html__('Carregando...', 'um-dia-no-parque'),
                'no_results' => $empty_text,
                'error'      => esc_html__('Erro ao carregar dados.', 'um-dia-no-parque'),
                'open'       => esc_html__('Aberto', 'um-dia-no-parque'),
                'closed'     => esc_html__('Fechado', 'um-dia-no-parque'),
                'maintenance' => esc_html__('Em Manutenção', 'um-dia-no-parque'),
                'more_info'  => $popup_link_text,
            ),
        );

        // Wrapper classes.
        $wrapper_classes = array(
            'umdnp-mapa-wrapper',
        );

        // Enqueue Leaflet assets.
        wp_enqueue_style('leaflet');
        wp_enqueue_style('leaflet-cluster');
        wp_enqueue_style('leaflet-cluster-default');
        wp_enqueue_script('leaflet');
        wp_enqueue_script('leaflet-cluster');

        // Ensure Dashicons are available for marker icons on frontend.
        wp_enqueue_style('dashicons');

        if ($show_parque_list) {
            $wrapper_classes[] = 'umdnp-mapa-com-lista';
            $wrapper_classes[] = 'umdnp-mapa-lista-' . $list_position;
        }

        ?><script type="application/json" id="<?php echo esc_attr($json_id); ?>"><?php echo wp_json_encode($map_data); ?></script>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-umdnp-map="<?php echo esc_attr($this->get_id()); ?>">

            <?php $this->render_filters(); ?>

            <!-- Mapa + Lista -->
            <div class="umdnp-mapa-content">
                <div class="umdnp-mapa-container" id="<?php echo esc_attr($map_id); ?>"
                     style="height: <?php echo esc_attr($map_height_size . $map_height_unit); ?>;">
                    <?php if (\Elementor\Plugin::$instance->editor->is_edit_mode()) : ?>
                        <!-- Editor preview: static placeholder (JS/map not available) -->
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#fafafa;border:2px dashed #ddd;border-radius:8px;text-align:center;padding:20px;">
                            <div>
                                <span class="dashicons dashicons-location" style="font-size:48px;width:48px;height:48px;color:#0073aa;display:block;margin:0 auto 10px;"></span>
                                <p style="margin:0;font-size:15px;color:#666;font-weight:500;"><?php echo esc_html__('Mapa Interativo', 'um-dia-no-parque'); ?></p>
                                <p style="margin:5px 0 0;font-size:12px;color:#999;"><?php echo esc_html__('Funciona apenas na página publicada (frontend).', 'um-dia-no-parque'); ?></p>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="umdnp-mapa-loading">
                            <span><?php echo esc_html($loading_text); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($show_parque_list) : ?>
                    <div class="umdnp-mapa-lista">
                        <div class="umdnp-mapa-lista-header">
                            <span class="umdnp-mapa-lista-count">0</span>
                            <span class="umdnp-mapa-lista-label"><?php echo esc_html__('parques encontrados', 'um-dia-no-parque'); ?></span>
                        </div>
                        <div class="umdnp-mapa-lista-itens">
                            <p class="umdnp-mapa-lista-placeholder"><?php echo esc_html($initial_text); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /**
     * Render widget output in the E\Elementor editor (live preview).
     *
     * Uses a simplified preview since the map requires JavaScript
     * and AJAX data that aren't available in the editor.
     *
     * @since 1.0.0
     */
    protected function content_template() {
        ?>
        <#
        var mapHeight = settings.map_height.size ? settings.map_height.size + (settings.map_height.unit || 'px') : '500px';
        var showList = settings.show_parque_list === 'yes';
        var listPosition = settings.list_position || 'right';
        var loadingText = settings.loading_text || '<?php echo esc_js(__('Carregando mapa...', 'um-dia-no-parque')); ?>';

        var wrapperClasses = 'umdnp-mapa-wrapper';
        if (showList) {
            wrapperClasses += ' umdnp-mapa-com-lista umdnp-mapa-lista-' + listPosition;
        }
        #>
        <div class="{{ wrapperClasses }}">
            <div class="umdnp-mapa-filtros" style="background:#f9f9f9;padding:15px;border-radius:8px;margin-bottom:15px;">
                <div class="umdnp-mapa-filtros-grid" style="display:flex;flex-wrap:wrap;gap:12px;">
                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-busca" style="flex:2 1 300px;min-width:200px;">
                        <input type="text" class="umdnp-mapa-filtro-input umdnp-mapa-busca-input" disabled
                               placeholder="<?php echo esc_js(__('Buscar por CEP, parque, cidade ou unidade...', 'um-dia-no-parque')); ?>"
                               style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;color:#999;">
                    </div>
                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-parque" style="flex:1 1 160px;min-width:130px;">
                        <select class="umdnp-mapa-filtro-select" disabled style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;color:#999;">
                            <option value=""><?php echo esc_js(__('Parques', 'um-dia-no-parque')); ?></option>
                        </select>
                    </div>
                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-unidade" style="flex:1 1 160px;min-width:130px;">
                        <select class="umdnp-mapa-filtro-select" disabled style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;color:#999;">
                            <option value=""><?php echo esc_js(__('Biomas', 'um-dia-no-parque')); ?></option>
                        </select>
                    </div>
                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-cidade" style="flex:1 1 160px;min-width:130px;">
                        <select class="umdnp-mapa-filtro-select" disabled style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;color:#999;">
                            <option value=""><?php echo esc_js(__('Cidades', 'um-dia-no-parque')); ?></option>
                        </select>
                    </div>
                    <div class="umdnp-mapa-filtro-item umdnp-mapa-filtro-tipo-atividade" style="flex:1 1 160px;min-width:130px;">
                        <select class="umdnp-mapa-filtro-select" disabled style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;color:#999;">
                            <option value=""><?php echo esc_js(__('Tipos de Atividade', 'um-dia-no-parque')); ?></option>
                        </select>
                    </div>
                <div style="flex:0 0 auto;">
                    <button type="button" class="umdnp-mapa-btn-buscar" disabled style="padding:10px 24px;background:#0073aa;color:#fff;border:none;border-radius:4px;font-size:14px;cursor:default;opacity:0.7;">
                        <?php echo esc_js(__('Buscar', 'um-dia-no-parque')); ?>
                    </button>
                </div>
                </div>
            </div>

            <div class="umdnp-mapa-content" style="display:flex;gap:15px;">
                <div class="umdnp-mapa-container" style="flex:1;min-height:300px;height:{{ mapHeight }};border-radius:8px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;background:#fafafa;">
                    <div style="text-align:center;padding:40px;">
                        <span class="dashicons dashicons-location" style="font-size:48px;width:48px;height:48px;color:#0073aa;display:block;margin:0 auto 15px;"></span>
                        <p style="margin:0;font-size:16px;color:#666;font-weight:500;"><?php echo esc_js(__('Mapa Interativo', 'um-dia-no-parque')); ?></p>
                        <p style="margin:5px 0 0;font-size:13px;color:#999;"><?php echo esc_js(__('Prévia disponível apenas no frontend.', 'um-dia-no-parque')); ?></p>
                        <p style="margin:15px 0 0;font-size:12px;color:#bbb;">
                            <# print(loadingText); #>
                        </p>
                    </div>
                </div>

                <# if (showList) { #>
                <div class="umdnp-mapa-lista" style="width:320px;min-width:280px;border:1px solid #e0e0e0;border-radius:8px;background:#fff;">
                    <div class="umdnp-mapa-lista-header" style="padding:14px 16px;background:#f5f5f5;border-bottom:1px solid #e0e0e0;font-size:14px;">
                        <span class="umdnp-mapa-lista-count" style="font-weight:700;color:#0073aa;font-size:18px;">0</span>
                        <span class="umdnp-mapa-lista-label" style="color:#666;"><?php echo esc_js(__('parques encontrados', 'um-dia-no-parque')); ?></span>
                    </div>
                    <div class="umdnp-mapa-lista-itens" style="padding:20px;text-align:center;color:#999;font-size:13px;">
                        <p class="umdnp-mapa-lista-placeholder" style="margin:0;">
                            {{ settings.initial_text || '<?php echo esc_js(__('Selecione filtros para buscar parques.', 'um-dia-no-parque')); ?>' }}
                        </p>
                    </div>
                </div>
                <# } #>
            </div>
        </div>
        <?php
    }
}
