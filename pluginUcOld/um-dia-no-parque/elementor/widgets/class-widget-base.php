<?php
/**
 * Abstract base class for all Um Dia No Parque Elementor widgets.
 *
 * Centralizes common boilerplate: categories, wrapper, style/script deps,
 * and helper methods for control registration.
 *
 * @since      1.9.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Um_Dia_No_Parque_Widget_Base extends \Elementor\Widget_Base {

    public function get_categories() {
        return array('um-dia-no-parque');
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    public function get_style_depends() {
        return array('um-dia-no-parque-elementor-widgets');
    }

    public function get_script_depends() {
        return array('um-dia-no-parque-elementor-widgets');
    }

    protected function add_switcher_control($key, $label, $default = 'yes') {
        $this->add_control(
            $key,
            array(
                'label'        => $label,
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('Sim', 'um-dia-no-parque'),
                'label_off'    => esc_html__('Não', 'um-dia-no-parque'),
                'return_value' => 'yes',
                'default'      => $default,
            )
        );
    }

    protected function add_border_control($name, $selector) {
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => $name,
                'label'    => esc_html__('Borda', 'um-dia-no-parque'),
                'selector' => $selector,
            )
        );
    }

    protected function add_shadow_control($name, $selector) {
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => $name,
                'label'    => esc_html__('Sombra', 'um-dia-no-parque'),
                'selector' => $selector,
            )
        );
    }

    protected function add_typography_control($name, $selector) {
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => $name,
                'label'    => esc_html__('Tipografia', 'um-dia-no-parque'),
                'selector' => $selector,
            )
        );
    }

    protected function add_dimensions_control($name, $selector, $property = 'padding', $size_units = array('px', 'em')) {
        $this->add_responsive_control(
            $name,
            array(
                'label'      => esc_html__(ucfirst($property), 'um-dia-no-parque'),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => $size_units,
                'selectors'  => array(
                    $selector => "{$property}: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};",
                ),
            )
        );
    }

    protected function is_yes($settings, $key) {
        return !empty($settings[$key]) && 'yes' === $settings[$key];
    }

    protected function get_setting($settings, $key, $default = '') {
        return !empty($settings[$key]) ? $settings[$key] : $default;
    }
}
