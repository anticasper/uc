<?php
/**
 * Elementor Pro Dynamic Tag: Page URL
 *
 * Outputs the URL to a plugin page. The user selects which page type
 * from a dropdown control in the Elementor editor.
 *
 * @since      1.7.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/dynamic-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page URL dynamic tag — Elementor 4.x compatible.
 *
 * Extends Elementor Core's Data_Tag (URL category) so it works in
 * link/URL fields. The user picks which archive page to link to
 * via a select control.
 *
 * @since 1.7.0
 */
class Um_Dia_No_Parque_Tag_Page_URL extends \Elementor\Core\DynamicTags\Data_Tag {

    /**
     * Tag name.
     *
     * @since  1.7.0
     * @return string
     */
    public function get_name() {
        return 'umdnp-page-url';
    }

    /**
     * Tag title.
     *
     * @since  1.7.0
     * @return string
     */
    public function get_title() {
        return esc_html__('URL de Página do Plugin', 'um-dia-no-parque');
    }

    /**
     * Tag group.
     *
     * @since  1.7.0
     * @return string
     */
    public function get_group() {
        return 'um-dia-no-parque';
    }

    /**
     * Tag categories — URL category for use in link/URL fields.
     *
     * @since  1.7.0
     * @return array
     */
    public function get_categories() {
        return array(\ElementorPro\Modules\DynamicTags\Module::URL_CATEGORY);
    }

    /**
     * Register controls — a select to choose which page.
     *
     * @since 1.7.0
     */
    protected function register_controls() {
        $this->add_control(
            'page_type',
            array(
                'label'   => esc_html__('Página', 'um-dia-no-parque'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_page_options(),
                'default' => 'archive-uc',
            )
        );
    }

    /**
     * Get the list of available plugin pages.
     *
     * Only includes post types that have `has_archive` enabled.
     *
     * @since  1.7.0
     * @return array
     */
    private function get_page_options() {
        $pages = array(
            'archive-uc'          => esc_html__('Arquivo de UCs', 'um-dia-no-parque'),
            'archive-atividade'   => esc_html__('Arquivo de Atividades', 'um-dia-no-parque'),
            'archive-depoimento'  => esc_html__('Arquivo de Depoimentos', 'um-dia-no-parque'),
            'archive-parceiro'    => esc_html__('Arquivo de Parceiros', 'um-dia-no-parque'),
            'archive-uf'          => esc_html__('Arquivo de UFs', 'um-dia-no-parque'),
            'archive-oque-levar'  => esc_html__('Arquivo de O que Levar', 'um-dia-no-parque'),
        );

        return $pages;
    }

    /**
     * Get the tag value (the URL).
     *
     * @since  1.7.0
     * @param  array $options Options.
     * @return string
     */
    public function get_value(array $options = array()) {
        $page_type = $this->get_settings('page_type');

        if (empty($page_type)) {
            return '';
        }

        // Map page_type slugs to post types.
        $post_types = array(
            'archive-uc'          => 'uc',
            'archive-atividade'   => 'atividade',
            'archive-depoimento'  => 'depoimento',
            'archive-parceiro'    => 'parceiro',
            'archive-uf'          => 'uf',
            'archive-oque-levar'  => 'oque_levar',
        );

        if (!isset($post_types[$page_type])) {
            return '';
        }

        $post_type = $post_types[$page_type];

        // Only return URL if the post type has an archive.
        $pt_object = get_post_type_object($post_type);
        if (!$pt_object || empty($pt_object->has_archive)) {
            return '';
        }

        $link = get_post_type_archive_link($post_type);
        return $link ? $link : '';
    }
}
