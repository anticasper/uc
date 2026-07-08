<?php
/**
 * Elementor Pro Dynamic Tag: UC Meta
 *
 * Exibe metadados da Unidade de Conservação atual via uma única tag
 * com controle de seleção do campo desejado.
 *
 * Compatível com Elementor 4.x (Module\DynamicTags\Tags\Base\Tag).
 *
 * @since      1.4.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/dynamic-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UC Meta dynamic tag — Elementor 4.x compatible.
 *
 * Tag única com controle SELECT para escolher o metadado da UC a exibir.
 *
 * @since 1.4.0
 */
class Um_Dia_No_Parque_Tag_UC_Meta extends \ElementorPro\Modules\DynamicTags\Tags\Base\Tag {

    /**
     * Tag name.
     *
     * @since  1.4.0
     * @return string
     */
    public function get_name() {
        return 'uc-meta';
    }

    /**
     * Tag title.
     *
     * @since  1.4.0
     * @return string
     */
    public function get_title() {
        return esc_html__('Meta da UC', 'um-dia-no-parque');
    }

    /**
     * Tag group.
     *
     * @since  1.4.0
     * @return string
     */
    public function get_group() {
        return 'um-dia-no-parque';
    }

    /**
     * Tag categories.
     *
     * @since  1.4.0
     * @return array
     */
    public function get_categories() {
        return array(\ElementorPro\Modules\DynamicTags\Module::TEXT_CATEGORY);
    }

    /**
     * Register controls — select para escolher o campo.
     *
     * @since 1.4.0
     */
    protected function register_controls() {
        $this->add_control(
            'meta_field',
            array(
                'label'   => esc_html__('Campo', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_meta_options(),
                'default' => 'nome',
            )
        );
    }

    /**
     * Available meta field options.
     *
     * @since  1.7.0
     * @return array
     */
    private function get_meta_options() {
        return array(
            'nome'     => esc_html__('Nome da UC', 'um-dia-no-parque'),
            'cidade'   => esc_html__('Cidade da UC', 'um-dia-no-parque'),
            'email'    => esc_html__('Email da UC', 'um-dia-no-parque'),
            'telefone' => esc_html__('Telefone da UC', 'um-dia-no-parque'),
            'orgao'    => esc_html__('Órgão Responsável', 'um-dia-no-parque'),
        );
    }

    /**
     * Render the tag value.
     *
     * @since 1.4.0
     */
    public function render() {
        $field = $this->get_settings('meta_field');

        if (empty($field)) {
            return;
        }

        $value = '';

        switch ($field) {
            case 'nome':
                $value = get_the_title(get_the_ID());
                break;

            case 'cidade':
                $city_terms = wp_get_object_terms(get_the_ID(), 'cidade', array('fields' => 'names'));
                $value = !empty($city_terms) ? $city_terms[0] : '';
                break;

            default:
                $meta_key = $this->get_meta_key_for_field($field);
                if ($meta_key) {
                    $value = get_post_meta(get_the_ID(), $meta_key, true);
                }
                break;
        }

        echo wp_kses_post($value);
    }

    /**
     * Map field slug to meta key constant.
     *
     * @since  1.7.0
     * @param  string $field Field slug.
     * @return string|null
     */
    private function get_meta_key_for_field($field) {
        $map = array(
            'email'    => Um_Dia_No_Parque_Meta::UC_EMAIL,
            'telefone' => Um_Dia_No_Parque_Meta::UC_WHATSAPP,
            'orgao'    => Um_Dia_No_Parque_Meta::UC_RESPONSAVEL,
        );

        return isset($map[$field]) ? $map[$field] : null;
    }
}
