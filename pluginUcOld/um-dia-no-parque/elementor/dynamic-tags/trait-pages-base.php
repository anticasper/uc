<?php
/**
 * Shared trait: Dynamic Tag — Pages Base
 *
 * Fornece métodos comuns entre as tags "Conteúdo das Páginas"
 * (TEXT_CATEGORY) e "Imagem das Páginas" (IMAGE_CATEGORY):
 * - Registro do controle de grupo (tab_group)
 * - Registro padronizado dos 4 campos condicionais por grupo
 * - Leitura do valor salvo em `um_dia_no_parque_pages`
 *
 * @since      1.8.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/dynamic-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait compartilhado para tags de páginas do plugin.
 *
 * @since 1.8.0
 */
trait Um_Dia_No_Parque_Tag_Pages_Base {

    /**
     * Get the tab group options (shared across all page tags).
     *
     * @since  1.8.0
     * @return array
     */
    protected function get_tab_group_options() {
        return array(
            'home'         => esc_html__('Home', 'um-dia-no-parque'),
            'atividades'   => esc_html__('Atividades', 'um-dia-no-parque'),
            'experiencias' => esc_html__('Experiências', 'um-dia-no-parque'),
            'movimento'    => esc_html__('O Movimento', 'um-dia-no-parque'),
        );
    }

    /**
     * Register the tab_group select control (shared).
     *
     * @since 1.8.0
     */
    protected function register_tab_group_control() {
        $this->add_control(
            'tab_group',
            array(
                'label'   => esc_html__('Grupo', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_tab_group_options(),
                'default' => 'home',
            )
        );
    }

    /**
     * Register conditional field controls for each group.
     *
     * Estrutura esperada de $group_fields:
     *   array(
     *     'home' => array(
     *       'label'       => 'Campo (Home)',
     *       'options'     => array( ... ),
     *       'default'     => 'home_banner_slide_title',
     *     ),
     *     'atividades' => array(
     *       'label'       => 'Campo (Atividades)',
     *       'options'     => array( ... ),
     *       'default'     => 'atividades_title',
     *     ),
     *     ...
     *   )
     *
     * @since  1.8.0
     * @param  array $group_fields Map of group => field config.
     */
    protected function register_group_field_controls(array $group_fields) {
        $group_keys = array_keys($this->get_tab_group_options());

        foreach ($group_keys as $group) {
            if (!isset($group_fields[$group])) {
                continue;
            }

            $config = $group_fields[$group];

            $this->add_control(
                $group . '_field',
                array(
                    'label'     => $config['label'],
                    'type'      => \Elementor\Controls_Manager::SELECT,
                    'options'   => $config['options'],
                    'default'   => $config['default'],
                    'condition' => array('tab_group' => $group),
                )
            );
        }
    }

    /**
     * Read a field value from the um_dia_no_parque_pages option.
     *
     * Retorna o raw value salvo, ou null se não existir.
     *
     * @since  1.8.0
     * @param  string $field Field key.
     * @return mixed|null
     */
    protected function get_pages_field_value($field) {
        if (empty($field)) {
            return null;
        }

        $pages = get_option('um_dia_no_parque_pages', array());

        if (!isset($pages[$field]) || '' === $pages[$field]) {
            return null;
        }

        return $pages[$field];
    }

    /**
     * Resolve the selected field key based on tab_group setting.
     *
     * Lê tab_group, monta $field_key = "{tab_group}_field",
     * e retorna o valor salvo em um_dia_no_parque_pages.
     *
     * @since  1.8.0
     * @return string|null
     */
    protected function get_selected_field() {
        $tab_group = $this->get_settings('tab_group');

        if (empty($tab_group)) {
            return null;
        }

        $field_key = $tab_group . '_field';
        $field     = $this->get_settings($field_key);

        if (empty($field)) {
            return null;
        }

        return $field;
    }
}
