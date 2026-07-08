<?php
/**
 * Elementor Widget: Carrossel de Atividades da UC
 *
 * Exibe as atividades relacionadas à Unidade de Conservação atual
 * em formato de carrossel (Swiper) com informações de data, horário,
 * descrição, dificuldade e público-alvo.
 *
 * @since      1.8.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Carrossel de Atividades da UC — Elementor Widget.
 *
 * @since 1.8.0
 */
class Um_Dia_No_Parque_Widget_Atividades_Carousel extends Um_Dia_No_Parque_Widget_Base {

    public function get_name() {
        return 'um-dia-no-parque-atividades-carousel';
    }

    public function get_title() {
        return esc_html__('Carrossel de Atividades da UC', 'um-dia-no-parque');
    }

    public function get_icon() {
        return 'eicon-media-carousel';
    }

    public function get_keywords() {
        return array(
            'atividade', 'activity', 'carrossel', 'carousel',
            'slider', 'evento', 'event', 'uc', 'unidade',
            'conservação', 'um dia no parque',
        );
    }

    /**
     * Register widget controls.
     *
     * @since 1.8.0
     */
    protected function register_controls() {

        // ================================================================
        // 1. CONTENT TAB — Carousel Settings
        // ================================================================
        $this->start_controls_section(
            'carousel_section',
            array(
                'label' => esc_html__('Configuração do Carrossel', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_responsive_control(
            'slides_to_show',
            array(
                'label'           => esc_html__('Slides por Visualização', 'um-dia-no-parque'),
                'type'            => \Elementor\Controls_Manager::NUMBER,
                'min'             => 1,
                'max'             => 6,
                'step'            => 1,
                'devices'         => array('desktop', 'tablet', 'mobile'),
                'desktop_default' => 3,
                'tablet_default'  => 2,
                'mobile_default'  => 1,
            )
        );

        $this->add_control(
            'slides_to_scroll',
            array(
                'label'   => esc_html__('Slides para Rolar', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 6,
                'step'    => 1,
                'default' => 1,
            )
        );

        $this->add_control(
            'navigation',
            array(
                'label'        => esc_html__('Setas de Navegação', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'pagination',
            array(
                'label'        => esc_html__('Paginação (Pontinhos)', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'autoplay',
            array(
                'label'        => esc_html__('Autoplay', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'autoplay_speed',
            array(
                'label'     => esc_html__('Velocidade do Autoplay (ms)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::NUMBER,
                'default'   => 5000,
                'min'       => 1000,
                'max'       => 15000,
                'step'      => 500,
                'condition' => array(
                    'autoplay' => 'yes',
                ),
            )
        );

        $this->add_control(
            'infinite',
            array(
                'label'        => esc_html__('Loop Infinito', 'um-dia-no-parque'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => 'yes',
            )
        );

        $this->add_control(
            'speed',
            array(
                'label'   => esc_html__('Velocidade da Transição (ms)', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 500,
                'min'     => 100,
                'max'     => 5000,
                'step'    => 100,
            )
        );

        $this->add_control(
            'image_spacing',
            array(
                'label'      => esc_html__('Espaçamento entre Slides (px)', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::NUMBER,
                'default'    => 10,
                'min'        => 0,
                'max'        => 100,
                'step'       => 1,
                'selectors'  => array(
                    '{{WRAPPER}} .swiper-wrapper' => 'gap: 0;',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 2. CONTENT TAB — Card Content
        // ================================================================
        $this->start_controls_section(
            'card_content_section',
            array(
                'label' => esc_html__('Conteúdo do Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label'       => esc_html__('Texto do Botão', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('INSCREVA-SE', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'button_link',
            array(
                'label'       => esc_html__('Link do Botão', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::URL,
                'placeholder' => esc_html__('https://seusite.com.br/inscricao', 'um-dia-no-parque'),
                'default'     => array(
                    'url' => '',
                ),
                'description'  => esc_html__('URL para inscrição nas atividades. Deixe vazio para não exibir o botão.', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'button_target',
            array(
                'label'     => esc_html__('Abrir Link em', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '_blank',
                'options'   => array(
                    '_self'  => esc_html__('Mesma janela', 'um-dia-no-parque'),
                    '_blank' => esc_html__('Nova janela', 'um-dia-no-parque'),
                ),
                'condition' => array(
                    'button_link[url]!' => '',
                ),
            )
        );

        $this->add_control(
            'empty_message',
            array(
                'label'       => esc_html__('Mensagem sem Atividades', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => esc_html__('Nenhuma atividade encontrada para esta unidade.', 'um-dia-no-parque'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 3. STYLE TAB — Card
        // ================================================================
        $this->start_controls_section(
            'style_card',
            array(
                'label' => esc_html__('Card', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_bg_color',
            array(
                'label'     => esc_html__('Cor de Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default'    => array(
                    'top'      => '25',
                    'right'    => '20',
                    'bottom'   => '25',
                    'left'     => '20',
                    'unit'     => 'px',
                    'isLinked' => false,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-atividade-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'card_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top'      => '8',
                    'right'    => '8',
                    'bottom'   => '8',
                    'left'     => '8',
                    'unit'     => 'px',
                    'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-atividade-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'card_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-card',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'card_box_shadow',
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-card',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 4. STYLE TAB — Title
        // ================================================================
        $this->start_controls_section(
            'style_title',
            array(
                'label' => esc_html__('Título da Atividade', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label'     => esc_html__('Cor', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-card-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'title_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-card-title',
            )
        );

        $this->add_responsive_control(
            'title_margin',
            array(
                'label'      => esc_html__('Margem Inferior', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range'      => array(
                    'px' => array('min' => 0, 'max' => 40, 'step' => 1),
                ),
                'default'    => array('unit' => 'px', 'size' => 10),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-atividade-card-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 5. STYLE TAB — Meta Labels
        // ================================================================
        $this->start_controls_section(
            'style_meta_label',
            array(
                'label' => esc_html__('Rótulos (Data, Descrição, Dificuldade, Público)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'meta_label_color',
            array(
                'label'     => esc_html__('Cor dos Rótulos', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#f2295b',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-meta-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'meta_label_typography',
                'label'    => esc_html__('Tipografia dos Rótulos', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-meta-label',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 6. STYLE TAB — Meta Values
        // ================================================================
        $this->start_controls_section(
            'style_meta_value',
            array(
                'label' => esc_html__('Valores (Horário, Descrição, Dificuldade, Público)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'meta_value_color',
            array(
                'label'     => esc_html__('Cor dos Valores', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#666666',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-meta-value' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'meta_value_typography',
                'label'    => esc_html__('Tipografia dos Valores', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-meta-value',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 7. STYLE TAB — Button (INSCREVA-SE)
        // ================================================================
        $this->start_controls_section(
            'style_button',
            array(
                'label' => esc_html__('Botão INSCREVA-SE', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'button_typography',
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-btn',
            )
        );

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            array(
                'label' => esc_html__('Normal', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label'     => esc_html__('Cor do Texto', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_bg_color',
            array(
                'label'     => esc_html__('Cor de Fundo', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#f2295b',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'button_border',
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-btn',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            array(
                'label' => esc_html__('Hover', 'um-dia-no-parque'),
            )
        );

        $this->add_control(
            'button_hover_text_color',
            array(
                'label'     => esc_html__('Cor do Texto (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_bg_color',
            array(
                'label'     => esc_html__('Cor de Fundo (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#d11d4a',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_border_color',
            array(
                'label'     => esc_html__('Cor da Borda (Hover)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '',
                'selectors' => array(
                    '{{WRAPPER}} .umdnp-atividade-btn:hover' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'button_padding',
            array(
                'label'      => esc_html__('Padding', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default'    => array(
                    'top'      => '12',
                    'right'    => '16',
                    'bottom'   => '12',
                    'left'     => '16',
                    'unit'     => 'px',
                    'isLinked' => false,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-atividade-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
                'separator'  => 'before',
            )
        );

        $this->add_control(
            'button_radius',
            array(
                'label'      => esc_html__('Arredondamento', 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'default'    => array(
                    'top'      => '4',
                    'right'    => '4',
                    'bottom'   => '4',
                    'left'     => '4',
                    'unit'     => 'px',
                    'isLinked' => true,
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .umdnp-atividade-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'button_box_shadow',
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => '{{WRAPPER}} .umdnp-atividade-btn',
            )
        );

        $this->end_controls_section();

        // ================================================================
        // 8. STYLE TAB — Navigation (Arrows + Pagination)
        // ================================================================
        $this->start_controls_section(
            'style_navigation',
            array(
                'label' => esc_html__('Navegação (Setas e Paginação)', 'um-dia-no-parque'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'arrows_color',
            array(
                'label'     => esc_html__('Cor das Setas', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .swiper-button-next, {{WRAPPER}} .swiper-button-prev' => 'color: {{VALUE}};',
                ),
                'condition' => array(
                    'navigation' => 'yes',
                ),
            )
        );

        $this->add_control(
            'pagination_color',
            array(
                'label'     => esc_html__('Cor da Paginação (ativa)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#f2295b',
                'selectors' => array(
                    '{{WRAPPER}} .swiper-pagination-bullet-active' => 'background-color: {{VALUE}};',
                ),
                'condition' => array(
                    'pagination' => 'yes',
                ),
            )
        );

        $this->add_control(
            'pagination_inactive_color',
            array(
                'label'     => esc_html__('Cor da Paginação (inativa)', 'um-dia-no-parque'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#cccccc',
                'selectors' => array(
                    '{{WRAPPER}} .swiper-pagination-bullet' => 'background-color: {{VALUE}};',
                ),
                'condition' => array(
                    'pagination' => 'yes',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget output.
     *
     * @since 1.8.0
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Only works on single UC pages.
        if (!is_singular('uc')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding:40px;text-align:center;background:#f5f5f5;border:2px dashed #ddd;border-radius:8px;color:#999;">';
                echo '<p style="margin:0;font-size:16px;">' . esc_html__('🔒 Este widget exibe atividades relacionadas à UC atual. Visualize em uma página de Unidade de Conservação.', 'um-dia-no-parque') . '</p>';
                echo '</div>';
            }
            return;
        }

        $uc_id            = get_the_ID();
        $atividade_ids    = get_post_meta($uc_id, Um_Dia_No_Parque_Meta::UC_ATIVIDADE_IDS, true);

        if (empty($atividade_ids) || !is_array($atividade_ids)) {
            $this->render_empty($settings);
            return;
        }

        // Query only active atividades.
        $args = array(
            'post_type'      => 'atividade',
            'post__in'       => $atividade_ids,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'   => Um_Dia_No_Parque_Meta::ATIVIDADE_ATIVO,
                    'value' => '1',
                ),
            ),
        );

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            $this->render_empty($settings);
            wp_reset_postdata();
            return;
        }

        // Carousel settings.
        $slides_to_show      = !empty($settings['slides_to_show']) ? intval($settings['slides_to_show']) : 3;
        $slides_to_show_tab  = !empty($settings['slides_to_show_tablet']) ? intval($settings['slides_to_show_tablet']) : 2;
        $slides_to_show_mob  = !empty($settings['slides_to_show_mobile']) ? intval($settings['slides_to_show_mobile']) : 1;
        $slides_to_scroll    = !empty($settings['slides_to_scroll']) ? intval($settings['slides_to_scroll']) : 1;
        $show_arrows         = !empty($settings['navigation']) && 'yes' === $settings['navigation'];
        $show_pagination     = !empty($settings['pagination']) && 'yes' === $settings['pagination'];
        $autoplay            = !empty($settings['autoplay']) && 'yes' === $settings['autoplay'];
        $autoplay_speed      = !empty($settings['autoplay_speed']) ? intval($settings['autoplay_speed']) : 5000;
        $infinite            = !empty($settings['infinite']) && 'yes' === $settings['infinite'];
        $speed               = !empty($settings['speed']) ? intval($settings['speed']) : 500;
        $button_text         = !empty($settings['button_text']) ? $settings['button_text'] : __('INSCREVA-SE', 'um-dia-no-parque');
        $button_url          = !empty($settings['button_link']['url']) ? $settings['button_link']['url'] : '';
        $button_target       = !empty($settings['button_target']) ? $settings['button_target'] : '_blank';
        $gap                 = !empty($settings['image_spacing']) ? intval($settings['image_spacing']) : 10;
        $element_id          = $this->get_id();

        // Build config JSON for JS.
        $carousel_config = array(
            'slidesPerView'  => $slides_to_show_mob,
            'slidesPerGroup' => $slides_to_scroll,
            'spaceBetween'   => $gap,
            'speed'          => $speed,
            'loop'           => $infinite,
            'autoHeight'     => true,
            'breakpoints'    => array(
                '768'  => array(
                    'slidesPerView' => $slides_to_show_tab,
                ),
                '1025' => array(
                    'slidesPerView' => $slides_to_show,
                ),
            ),
        );

        if ($show_arrows) {
            $carousel_config['navigation'] = array(
                'nextEl' => '#umdnp-carousel-next-' . $element_id,
                'prevEl' => '#umdnp-carousel-prev-' . $element_id,
            );
        }

        if ($show_pagination) {
            $carousel_config['pagination'] = array(
                'el'        => '#umdnp-carousel-pagination-' . $element_id,
                'clickable' => true,
            );
        }

        if ($autoplay) {
            $carousel_config['autoplay'] = array(
                'delay'                => $autoplay_speed,
                'disableOnInteraction' => true,
            );
        }

        $wrapper_classes = 'umdnp-atividades-carousel-wrapper';
        if ($show_arrows) {
            $wrapper_classes .= ' has-nav-arrows';
        }
        if ($show_pagination) {
            $wrapper_classes .= ' has-pagination';
        }

        ?>
        <div class="<?php echo esc_attr($wrapper_classes); ?>" data-umdnp-carousel-id="<?php echo esc_attr($element_id); ?>">
            <?php if ($show_arrows) : ?>
                <div class="umdnp-carousel-nav-prev swiper-button-prev" id="umdnp-carousel-prev-<?php echo esc_attr($element_id); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </div>
            <?php endif; ?>

            <div class="umdnp-atividades-carousel swiper">
                <div class="swiper-wrapper">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <?php $this->render_slide(get_the_ID(), $settings); ?>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            </div>

            <?php if ($show_pagination) : ?>
                <div class="swiper-pagination" id="umdnp-carousel-pagination-<?php echo esc_attr($element_id); ?>"></div>
            <?php endif; ?>

            <?php if ($show_arrows) : ?>
                <div class="umdnp-carousel-nav-next swiper-button-next" id="umdnp-carousel-next-<?php echo esc_attr($element_id); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </div>
            <?php endif; ?>

            <script type="application/json" class="umdnp-carousel-config"><?php echo wp_json_encode($carousel_config); ?></script>
        </div>
        <?php
    }

    /**
     * Render a single atividade slide.
     *
     * @since 1.8.0
     * @param int   $post_id  Atividade post ID.
     * @param array $settings Widget settings.
     */
    private function render_slide($post_id, $settings) {
        $title        = get_the_title($post_id);
        $data         = get_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DATA, true);
        $horario      = get_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_HORARIO, true);
        $descricao    = get_post_meta($post_id, Um_Dia_No_Parque_Meta::ATIVIDADE_DESCRICAO, true);
        $dificuldades = get_the_terms($post_id, 'dificuldade');
        $publicos     = get_the_terms($post_id, 'publico');
        $button_text  = !empty($settings['button_text']) ? $settings['button_text'] : __('INSCREVA-SE', 'um-dia-no-parque');
        $button_url   = !empty($settings['button_link']['url']) ? $settings['button_link']['url'] : '';
        $button_target = !empty($settings['button_target']) ? $settings['button_target'] : '_blank';

        $dificuldade_names = $dificuldades ? implode(', ', wp_list_pluck($dificuldades, 'name')) : '';
        $publico_names     = $publicos ? implode(', ', wp_list_pluck($publicos, 'name')) : '';

        // Build time display.
        $horario_display = '';
        if ($data && $horario) {
            $horario_display = $data . ' — ' . $horario;
        } elseif ($data) {
            $horario_display = $data;
        } elseif ($horario) {
            $horario_display = $horario;
        }

        ?>
        <div class="swiper-slide">
            <div class="umdnp-atividade-card">
                <div class="umdnp-atividade-card-body">
                    <div class="umdnp-atividade-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f2295b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                            <polyline points="9 16 11 18 15 14"/>
                        </svg>
                    </div>

                    <h3 class="umdnp-atividade-card-title"><?php echo esc_html($title); ?></h3>

                    <?php if ($horario_display) : ?>
                        <div class="umdnp-atividade-meta">
                            <span class="umdnp-atividade-meta-label"><?php esc_html_e('Data e Horário', 'um-dia-no-parque'); ?></span>
                            <span class="umdnp-atividade-meta-value"><?php echo esc_html($horario_display); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($descricao) : ?>
                        <div class="umdnp-atividade-meta">
                            <span class="umdnp-atividade-meta-label"><?php esc_html_e('Descrição', 'um-dia-no-parque'); ?></span>
                            <span class="umdnp-atividade-meta-value"><?php echo esc_html($descricao); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($dificuldade_names) : ?>
                        <div class="umdnp-atividade-meta">
                            <span class="umdnp-atividade-meta-label"><?php esc_html_e('Dificuldade', 'um-dia-no-parque'); ?></span>
                            <span class="umdnp-atividade-meta-value"><?php echo esc_html($dificuldade_names); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($publico_names) : ?>
                        <div class="umdnp-atividade-meta">
                            <span class="umdnp-atividade-meta-label"><?php esc_html_e('Público', 'um-dia-no-parque'); ?></span>
                            <span class="umdnp-atividade-meta-value"><?php echo esc_html($publico_names); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($button_url) : ?>
                        <div class="umdnp-atividade-btn-wrapper">
                            <a href="<?php echo esc_url($button_url); ?>" target="<?php echo esc_attr($button_target); ?>" class="umdnp-atividade-btn">
                                <?php echo esc_html($button_text); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render empty state message.
     *
     * @since 1.8.0
     * @param array $settings Widget settings.
     */
    private function render_empty($settings) {
        $empty_text = !empty($settings['empty_message']) ? $settings['empty_message'] : __('Nenhuma atividade encontrada para esta unidade.', 'um-dia-no-parque');
        ?>
        <div class="umdnp-atividades-carousel-empty">
            <p><?php echo esc_html($empty_text); ?></p>
        </div>
        <?php
    }
}
