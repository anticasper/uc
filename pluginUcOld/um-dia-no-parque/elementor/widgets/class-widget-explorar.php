<?php
/**
 * Elementor Widget: Explorar Unidades de Conservação
 *
 * Widget com busca/filtros à esquerda e cards de UCs à direita,
 * permitindo explorar as unidades cadastradas.
 *
 * @since      1.7.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Explorar UCs — Elementor Widget.
 *
 * @since 1.7.0
 */
class Um_Dia_No_Parque_Widget_Explorar extends Um_Dia_No_Parque_Widget_Base {

    public function get_name() {
        return 'um-dia-no-parque-explorar';
    }

    public function get_title() {
        return esc_html__('Explorar Unidades de Conservação', 'um-dia-no-parque');
    }

    public function get_icon() {
        return 'eicon-site-search';
    }

    public function get_keywords() {
        return array(
            'explorar', 'explore', 'unidade', 'conservação', 'uc',
            'parque', 'park', 'card', 'grid', 'busca', 'search',
            'um dia no parque',
        );
    }

    protected function register_controls() {

        // ================================================================
        // 1. CONTENT TAB — Layout
        // ================================================================
        $this->start_controls_section(
            'layout_section',
            array(
                'label' => esc_html__('Layout', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'columns',
            array(
                'label'   => esc_html__('Colunas de Cards', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::CHOOSE,
                'default' => '3',
                'options' => array(
                    '2' => array(
                        'title' => esc_html__('2', 'um-dia-no-parque'),
                        'icon'  => 'eicon-num-2',
                    ),
                    '3' => array(
                        'title' => esc_html__('3', 'um-dia-no-parque'),
                        'icon'  => 'eicon-num-3',
                    ),
                    '4' => array(
                        'title' => esc_html__('4', 'um-dia-no-parque'),
                        'icon'  => 'eicon-num-4',
                    ),
                ),
                'toggle'  => false,
            )
        );

        $this->add_control(
            'max_cards',
            array(
                'label'   => esc_html__('Máximo de Cards', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min'     => 0,
                'max'     => 200,
                'step'    => 1,
                'description' => esc_html__('0 = todos os resultados.', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'orderby',
            array(
                'label'   => esc_html__('Ordenar por', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'title',
                'options' => array(
                    'title' => esc_html__('Nome (A-Z)', 'um-dia-no-parque'),
                    'date'  => esc_html__('Data de cadastro', 'um-dia-no-parque'),
                    'rand'  => esc_html__('Aleatório', 'um-dia-no-parque'),
                ),
            )
        );

        $this->add_control(
            'order',
            array(
                'label'   => esc_html__('Ordem', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => array(
                    'ASC'  => esc_html__('Crescente', 'um-dia-no-parque'),
                    'DESC' => esc_html__('Decrescente', 'um-dia-no-parque'),
                ),
                'condition' => array(
                    'orderby!' => 'rand',
                ),
            )
        );

        $this->add_control(
            'grid_gap',
            array(
                'label'      => esc_html__('Espaçamento entre Cards', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array('min' => 0, 'max' => 60, 'step' => 2),
                ),
                'default'    => array('unit' => 'px', 'size' => 20),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-grid' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 2. CONTENT TAB — Filters
        // ================================================================
        $this->start_controls_section(
            'filters_section',
            array(
                'label' => esc_html__('Filtros', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'show_search',
            array(
                'label'        => esc_html__('Busca por Texto', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'search_placeholder',
            array(
                'label'       => esc_html__('Placeholder da Busca', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Buscar por nome ou município...', 'um-dia-no-parque'),
                'condition'   => array('show_search' => 'yes'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'show_bioma_filter',
            array(
                'label'        => esc_html__('Filtro por Bioma', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'show_uf_filter',
            array(
                'label'        => esc_html__('Filtro por UF', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 2B. CONTENT TAB — Card
        // ================================================================
        $this->start_controls_section(
            'card_section',
            array(
                'label' => esc_html__('Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'default_image',
            array(
                'label'     => esc_html__('Imagem Padrão (fallback)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::MEDIA,
                'default'   => array(
                    'url' => '',
                ),
                'description' => esc_html__('Usada quando a UC não tem imagem própria.', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'desc_max_words',
            array(
                'label'     => esc_html__('Máx. de Palavras na Descrição', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::NUMBER,
                'default'   => 20,
                'min'       => 5,
                'max'       => 100,
                'step'      => 5,
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label'       => esc_html__('Mensagem "Nenhum Resultado"', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Nenhuma unidade de conservação encontrada.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'hide_sidebar_empty',
            array(
                'label'        => esc_html__('Ocultar Sidebar quando sem resultados', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'no',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 3. STYLE TAB — Sidebar
        // ================================================================
        $this->start_controls_section(
            'style_sidebar',
            array(
                'label' => esc_html__('Sidebar de Filtros', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'sidebar_width',
            array(
                'label'      => esc_html__('Largura', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range'      => array(
                    'px' => array('min' => 200, 'max' => 500),
                    '%'  => array('min' => 20,  'max' => 50),
                ),
                'default'    => array('unit' => 'px', 'size' => 280),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-sidebar' => 'width: {{SIZE}}{{UNIT}}; flex: 0 0 {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'sidebar_bg',
            array(
                'label'     => esc_html__('Cor de Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#f5f5f5',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-sidebar' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'sidebar_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default'    => array(
                    'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20',
                    'unit' => 'px', 'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-sidebar' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'sidebar_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8',
                    'unit' => 'px', 'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-sidebar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'sidebar_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-sidebar',
            )
        );

        $this->add_control(
            'sidebar_heading_color',
            array(
                'label'     => esc_html__('Cor dos Títulos dos Filtros', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-sidebar h4' => 'color: {{VALUE}};',
                ),
                'separator' => 'before',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'sidebar_heading_typography',
                'label'    => esc_html__('Tipografia dos Títulos', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-sidebar h4',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 4. STYLE TAB — Search Input
        // ================================================================
        $this->start_controls_section(
            'style_search',
            array(
                'label' => esc_html__('Campo de Busca', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'search_input_bg',
            array(
                'label'     => esc_html__('Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-search input' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'search_input_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-search input' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'search_input_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-search input',
            )
        );

        $this->add_responsive_control(
            'search_input_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6',
                    'unit' => 'px', 'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-search input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'search_input_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '10', 'right' => '14', 'bottom' => '10', 'left' => '14',
                    'unit' => 'px', 'isLinked' => false,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-search input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'search_input_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-search input',
            )
        );

        $this->add_control(
            'search_input_focus_color',
            array(
                'label'     => esc_html__('Cor da Borda ao Focar', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#0073aa',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-search input:focus' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 5. STYLE TAB — Cards
        // ================================================================
        $this->start_controls_section(
            'style_cards',
            array(
                'label' => esc_html__('Cards', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_bg',
            array(
                'label'     => esc_html__('Fundo do Card', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_bg_hover',
            array(
                'label'     => esc_html__('Fundo ao Hover', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'card_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-card',
            )
        );

        $this->add_control(
            'card_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10',
                    'unit' => 'px', 'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'card_shadow',
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-card',
            )
        );

        $this->add_responsive_control(
            'card_padding',
            array(
                'label'      => esc_html__('Padding do Corpo', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'card_content_align',
            array(
                'label'   => esc_html__('Alinhamento do Conteúdo', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::CHOOSE,
                'default' => 'left',
                'options' => array(
                    'left'   => array(
                        'title' => esc_html__('Esquerda', 'um-dia-no-parque'),
                        'icon'  => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => esc_html__('Centro', 'um-dia-no-parque'),
                        'icon'  => 'eicon-text-align-center',
                    ),
                    'right'  => array(
                        'title' => esc_html__('Direita', 'um-dia-no-parque'),
                        'icon'  => 'eicon-text-align-right',
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-body' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'content_vertical_align',
            array(
                'label'     => esc_html__('Alinhamento Vertical', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::CHOOSE,
                'default'   => 'flex-end',
                'toggle'    => false,
                'options'   => array(
                    'flex-start' => array(
                        'title' => esc_html__('Topo', 'um-dia-no-parque'),
                        'icon'  => 'eicon-v-align-top',
                    ),
                    'center'     => array(
                        'title' => esc_html__('Centro', 'um-dia-no-parque'),
                        'icon'  => 'eicon-v-align-middle',
                    ),
                    'flex-end'   => array(
                        'title' => esc_html__('Fundo', 'um-dia-no-parque'),
                        'icon'  => 'eicon-v-align-bottom',
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card--bg-image' => 'align-items: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'content_items_gap',
            array(
                'label'      => esc_html__('Espaço entre Itens', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array('min' => 0, 'max' => 30, 'step' => 2),
                ),
                'default'    => array('unit' => 'px', 'size' => 0),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card--bg-image .umdnp-explorar-card-body' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'card_transition',
            array(
                'label'     => esc_html__('Transição ao Hover', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => 'lift',
                'options'   => array(
                    'none'    => esc_html__('Nenhuma', 'um-dia-no-parque'),
                    'lift'    => esc_html__('Elevar', 'um-dia-no-parque'),
                    'scale'   => esc_html__('Escalar', 'um-dia-no-parque'),
                ),
                'prefix_class' => 'umdnp-explorar-hover-',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 6. STYLE TAB — Card Image (background-image)
        // ================================================================
        $this->start_controls_section(
            'style_card_image',
            array(
                'label' => esc_html__('Imagem do Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_image_fit',
            array(
                'label'   => esc_html__('Ajuste da Imagem', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'cover',
                'options' => array(
                    'cover'   => esc_html__('Cobrir (Cover)', 'um-dia-no-parque'),
                    'contain' => esc_html__('Conter (Contain)', 'um-dia-no-parque'),
                    '100% 100%' => esc_html__('Preencher (Fill)', 'um-dia-no-parque'),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card--bg-image' => 'background-size: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_image_overlay',
            array(
                'label'     => esc_html__('Overlay', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'description' => esc_html__(
                    'Cor sólida sobre a imagem. Deixe vazio para usar o gradiente padrão.',
                    'um-dia-no-parque'
                ),
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card--bg-image::after' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 7. STYLE TAB — Card Title
        // ================================================================
        $this->start_controls_section(
            'style_card_title',
            array(
                'label' => esc_html__('Título do Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_title_color',
            array(
                'label'     => esc_html__('Cor', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#1a1a1a',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_title_color_hover',
            array(
                'label'     => esc_html__('Cor ao Hover', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card:hover .umdnp-explorar-card-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'card_title_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-card-title',
            )
        );

        $this->add_responsive_control(
            'card_title_margin',
            array(
                'label'      => esc_html__('Margem Inferior', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array('px' => array('min' => 0, 'max' => 40)),
                'default'    => array('unit' => 'px', 'size' => 8),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 8. STYLE TAB — Card Description
        // ================================================================
        $this->start_controls_section(
            'style_card_desc',
            array(
                'label' => esc_html__('Descrição do Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_desc_color',
            array(
                'label'     => esc_html__('Cor', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#555555',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-desc' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'card_desc_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-card-desc',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 9. STYLE TAB — Card Button
        // ================================================================
        $this->start_controls_section(
            'style_card_button',
            array(
                'label' => esc_html__('Botão do Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_btn_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_btn_bg',
            array(
                'label'     => esc_html__('Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#0073aa',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_btn_color_hover',
            array(
                'label'     => esc_html__('Cor do Texto (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'card_btn_bg_hover',
            array(
                'label'     => esc_html__('Fundo (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#005a87',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'card_btn_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-card-btn',
            )
        );

        $this->add_responsive_control(
            'card_btn_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default'    => array(
                    'top' => '8', 'right' => '16', 'bottom' => '8', 'left' => '16',
                    'unit' => 'px', 'isLinked' => false,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'card_btn_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4',
                    'unit' => 'px', 'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_btn_margin_top',
            array(
                'label'      => esc_html__('Margem Superior', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array('px' => array('min' => 0, 'max' => 40)),
                'default'    => array('unit' => 'px', 'size' => 12),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'card_btn_display',
            array(
                'label'   => esc_html__('Exibição', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'inline-block',
                'options' => array(
                    'inline-block' => esc_html__('Inline', 'um-dia-no-parque'),
                    'block'        => esc_html__('Bloco (largura total)', 'um-dia-no-parque'),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-card-btn' => 'display: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 10. STYLE TAB — Filter Checkboxes / Labels
        // ================================================================
        $this->start_controls_section(
            'style_filter_items',
            array(
                'label' => esc_html__('Itens dos Filtros', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'filter_label_color',
            array(
                'label'     => esc_html__('Cor dos Labels', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#555555',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-filter-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'filter_label_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-filter-label',
            )
        );

        $this->add_responsive_control(
            'filter_items_gap',
            array(
                'label'      => esc_html__('Espaçamento entre Itens', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array('px' => array('min' => 0, 'max' => 20)),
                'default'    => array('unit' => 'px', 'size' => 6),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-filter-options' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 11. STYLE TAB — Empty Message
        // ================================================================
        $this->start_controls_section(
            'style_empty_message',
            array(
                'label' => esc_html__('Mensagem "Nenhum Resultado"', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'empty_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#888888',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-empty' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'empty_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-explorar-empty',
            )
        );

        $this->add_responsive_control(
            'empty_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20',
                    'unit' => 'px', 'isLinked' => false,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-explorar-empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'empty_bg',
            array(
                'label'     => esc_html__('Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-explorar-empty' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget output.
     *
     * @since 1.7.0
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        $columns = !empty($settings['columns']) ? intval($settings['columns']) : 3;
        $max     = !empty($settings['max_cards']) ? intval($settings['max_cards']) : -1;
        $orderby = !empty($settings['orderby']) ? $settings['orderby'] : 'title';
        $order   = !empty($settings['order']) ? $settings['order'] : 'ASC';

        // Query UCs
        $args = array(
            'post_type'      => 'uc',
            'posts_per_page' => $max > 0 ? $max : -1,
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
            'no_found_rows'  => true,
        );

        $query = new WP_Query($args);

        // Get filter data
        $biomas = get_terms(array('taxonomy' => 'bioma', 'hide_empty' => true));
        $ufs    = get_posts(array(
            'post_type'      => 'uf',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Build UF ID → sigla lookup map to avoid N+1 meta queries per card.
        $uf_sigla_map = array();
        if (!empty($ufs)) {
            foreach ($ufs as $uf_post) {
                $uf_sigla = get_post_meta($uf_post->ID, Um_Dia_No_Parque_Meta::UF_SIGLA, true);
                if ($uf_sigla) {
                    $uf_sigla_map[$uf_post->ID] = $uf_sigla;
                }
            }
        }

        $show_search       = !empty($settings['show_search']) && 'yes' === $settings['show_search'];
        $show_bioma_filter = !empty($settings['show_bioma_filter']) && 'yes' === $settings['show_bioma_filter'];
        $show_uf_filter    = !empty($settings['show_uf_filter']) && 'yes' === $settings['show_uf_filter'];
        $search_placeholder = !empty($settings['search_placeholder']) ? $settings['search_placeholder'] : __('Buscar por nome ou município...', 'um-dia-no-parque');

        $has_results = $query->have_posts();
        $hide_sidebar_empty = !empty($settings['hide_sidebar_empty']) && 'yes' === $settings['hide_sidebar_empty'];
        $show_sidebar = ($show_search || $show_bioma_filter || $show_uf_filter) && !($hide_sidebar_empty && !$has_results);

        $empty_text = !empty($settings['empty_message']) ? $settings['empty_message'] : __('Nenhuma unidade de conservação encontrada.', 'um-dia-no-parque');

        ?>
        <div class="umdnp-explorar-wrapper">
            <?php if ($show_sidebar) : ?>
                <aside class="umdnp-explorar-sidebar">
                    <?php if ($show_search) : ?>
                        <div class="umdnp-explorar-search">
                            <h4><?php esc_html_e('Buscar', 'um-dia-no-parque'); ?></h4>
                            <input type="text" class="umdnp-explorar-search-input" placeholder="<?php echo esc_attr($search_placeholder); ?>" data-filter="text">
                        </div>
                    <?php endif; ?>

                    <?php if ($show_bioma_filter && !empty($biomas) && !is_wp_error($biomas)) : ?>
                        <div class="umdnp-explorar-filter-group">
                            <h4><?php esc_html_e('Bioma', 'um-dia-no-parque'); ?></h4>
                            <div class="umdnp-explorar-filter-options" data-filter="bioma">
                                <?php foreach ($biomas as $bioma) : ?>
                                    <label class="umdnp-explorar-filter-label">
                                        <input type="checkbox" value="<?php echo esc_attr($bioma->slug); ?>">
                                        <?php echo esc_html($bioma->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_uf_filter && !empty($ufs)) : ?>
                        <div class="umdnp-explorar-filter-group">
                            <h4><?php esc_html_e('UF', 'um-dia-no-parque'); ?></h4>
                            <div class="umdnp-explorar-filter-options" data-filter="uf">
                                <?php foreach ($ufs as $uf) : ?>
                                    <?php $sigla = get_post_meta($uf->ID, Um_Dia_No_Parque_Meta::UF_SIGLA, true); ?>
                                    <label class="umdnp-explorar-filter-label">
                                        <input type="checkbox" value="<?php echo esc_attr($sigla ?: $uf->post_title); ?>">
                                        <?php echo esc_html($sigla ? $uf->post_title . ' (' . $sigla . ')' : $uf->post_title); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>

            <div class="umdnp-explorar-content">
                <div class="umdnp-explorar-grid" data-columns="<?php echo esc_attr($columns); ?>">
                    <?php if ($has_results) : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php $this->render_card(get_the_ID(), $settings, $uf_sigla_map); ?>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <p class="umdnp-explorar-empty"><?php echo esc_html($empty_text); ?></p>
                    <?php endif; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single UC card.
     *
     * @since 1.7.0
     * @param int   $post_id
     * @param array $settings
     * @param array $uf_sigla_map Pre-built UF ID → sigla lookup (evita N+1 queries).
     */
    private function render_card($post_id, $settings, $uf_sigla_map = array()) {
        $title      = get_the_title($post_id);
        $desc       = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_BREVE_DESCRICAO, true);
        $img_id     = get_post_meta($post_id, Um_Dia_No_Parque_Meta::UC_IMAGEM, true);
        $biomas     = get_the_terms($post_id, 'bioma');

        $default_image_id = !empty($settings['default_image']['id']) ? $settings['default_image']['id'] : 0;
        $desc_max_words   = !empty($settings['desc_max_words']) ? intval($settings['desc_max_words']) : 20;
        $card_img_id      = $img_id ?: $default_image_id;

        // City and UF from taxonomy (canonical).
        $city_term_ids = wp_get_object_terms($post_id, 'cidade', array('fields' => 'ids'));
        $municipio    = '';

        $uf_siglas   = array();
        if (!empty($city_term_ids) && !is_wp_error($city_term_ids)) {
            $city_term = get_term($city_term_ids[0]);
            if ($city_term) {
                $municipio = $city_term->name;
            }
            $uf_id = (int) get_term_meta($city_term_ids[0], '_cidade_uf', true);
            if ($uf_id > 0 && !empty($uf_sigla_map[$uf_id])) {
                $uf_siglas[] = $uf_sigla_map[$uf_id];
            }
        }
        $bioma_slugs = $biomas ? wp_list_pluck($biomas, 'slug') : array();
        $url         = get_permalink($post_id);

        // Background image URL for card.
        $bg_url = $card_img_id ? wp_get_attachment_image_url($card_img_id, 'medium_large') : '';
        $has_bg = $bg_url ? ' umdnp-explorar-card--bg-image' : '';

        // Build searchable text — inclui título, município, biomas e UF.
        $search_parts = array($title, $municipio);
        if (!empty($biomas)) {
            foreach ($biomas as $b) {
                $search_parts[] = $b->name;
            }
        }
        $search_parts = array_merge($search_parts, $uf_siglas);
        $search_text  = strtolower(implode(' ', $search_parts));
        ?>
        <div class="umdnp-explorar-card<?php echo $has_bg; ?>"
             style="<?php echo $bg_url ? 'background-image:url(' . esc_url($bg_url) . ');' : ''; ?>"
             data-search="<?php echo esc_attr($search_text); ?>"
             data-bioma="<?php echo esc_attr(implode(',', $bioma_slugs)); ?>"
             data-uf="<?php echo esc_attr(implode(',', $uf_siglas)); ?>">

            <div class="umdnp-explorar-card-body">
                <h3 class="umdnp-explorar-card-title">
                    <a href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($title); ?>
                    </a>
                </h3>

                <?php if ($desc) : ?>
                    <p class="umdnp-explorar-card-desc"><?php echo esc_html(wp_trim_words($desc, $desc_max_words, '…')); ?></p>
                <?php endif; ?>

                <a href="<?php echo esc_url($url); ?>" class="umdnp-explorar-card-btn">
                    <?php esc_html_e('Ver Atividades', 'um-dia-no-parque'); ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px;vertical-align:middle;"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        <?php
    }
}
