<?php
/**
 * Elementor Pro Dynamic Tag: Imagem das Páginas
 *
 * Tag dedicada para exibir imagens configuradas nas páginas do plugin
 * (Home, Atividades, Experiências, O Movimento). Compatível com o
 * widget de Imagem do Elementor (IMAGE_CATEGORY).
 *
 * @since      1.8.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/dynamic-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page Image dynamic tag — Elementor 4.x compatible.
 *
 * Exibe campos de imagem das páginas configuradas no admin
 * (Home, Atividades, Experiências, O Movimento).
 * Registrada como IMAGE_CATEGORY para uso em widgets de imagem.
 *
 * @since 1.8.0
 */
class Um_Dia_No_Parque_Tag_Page_Image extends \Elementor\Core\DynamicTags\Data_Tag {

    use Um_Dia_No_Parque_Tag_Pages_Base;

    /**
     * Tag name.
     *
     * @since  1.8.0
     * @return string
     */
    public function get_name() {
        return 'page-image';
    }

    /**
     * Tag title.
     *
     * @since  1.8.0
     * @return string
     */
    public function get_title() {
        return esc_html__('Imagem das Páginas', 'um-dia-no-parque');
    }

    /**
     * Tag group.
     *
     * @since  1.8.0
     * @return string
     */
    public function get_group() {
        return 'um-dia-no-parque';
    }

    /**
     * Tag categories — image category for use in Image widgets.
     *
     * @since  1.8.0
     * @return array
     */
    public function get_categories() {
        return array(\ElementorPro\Modules\DynamicTags\Module::IMAGE_CATEGORY);
    }

    /**
     * Register controls — uses shared trait for group + field controls.
     *
     * @since 1.8.0
     */
    protected function register_controls() {
        $this->register_tab_group_control();

        $this->register_group_field_controls(array(
            'home' => array(
                'label'   => esc_html__('Imagem (Home)', 'um-dia-no-parque'),
                'options' => $this->get_home_image_options(),
                'default' => 'home_o_que_e_img1',
            ),
            'atividades' => array(
                'label'   => esc_html__('Imagem (Atividades)', 'um-dia-no-parque'),
                'options' => $this->get_tab_image_options('atividades'),
                'default' => 'atividades_hero_image',
            ),
            'experiencias' => array(
                'label'   => esc_html__('Imagem (Experiências)', 'um-dia-no-parque'),
                'options' => $this->get_tab_image_options('experiencias'),
                'default' => 'experiencias_hero_image',
            ),
            'movimento' => array(
                'label'   => esc_html__('Imagem (O Movimento)', 'um-dia-no-parque'),
                'options' => $this->get_tab_image_options('movimento'),
                'default' => 'movimento_hero_image',
            ),
        ));
    }

    /**
     * Get available image field options for the Home tab.
     *
     * @since  1.8.0
     * @return array
     */
    public function get_home_image_options() {
        return array(
            'home_o_que_e_img1'        => esc_html__('[O que é] Imagem 1', 'um-dia-no-parque'),
            'home_o_que_e_img2'        => esc_html__('[O que é] Imagem 2', 'um-dia-no-parque'),
            'home_o_que_e_img3'        => esc_html__('[O que é] Imagem 3', 'um-dia-no-parque'),
            'home_maior_movimento_img1' => esc_html__('[Movimento] Imagem 1', 'um-dia-no-parque'),
            'home_maior_movimento_img2' => esc_html__('[Movimento] Imagem 2', 'um-dia-no-parque'),
            'home_maior_movimento_img3' => esc_html__('[Movimento] Imagem 3', 'um-dia-no-parque'),
            'home_cta_image'            => esc_html__('[CTA] Imagem', 'um-dia-no-parque'),
            'home_como_participar_card1_icone' => esc_html__('[Como Participar] Card 1 — Ícone', 'um-dia-no-parque'),
            'home_como_participar_card2_icone' => esc_html__('[Como Participar] Card 2 — Ícone', 'um-dia-no-parque'),
            'home_como_participar_card3_icone' => esc_html__('[Como Participar] Card 3 — Ícone', 'um-dia-no-parque'),
            'home_como_participar_card4_icone' => esc_html__('[Como Participar] Card 4 — Ícone', 'um-dia-no-parque'),
        );
    }

    /**
     * Get available image field options for a simple tab.
     *
     * @since  1.8.0
     * @param  string $prefix Tab prefix (atividades, experiencias, movimento).
     * @return array
     */
    public function get_tab_image_options($prefix) {
        $labels = array(
            'hero_image' => esc_html__('Imagem de Destaque (Hero)', 'um-dia-no-parque'),
        );

        $options = array();
        foreach ($labels as $key => $label) {
            $options["{$prefix}_{$key}"] = $label;
        }

        return $options;
    }

    /**
     * Get the tag value (image data).
     *
     * Retorna o array de dados da imagem para o widget de Imagem do Elementor.
     *
     * @since  1.8.0
     * @param  array $options Options.
     * @return array
     */
    public function get_value(array $options = array()) {
        $field = $this->get_selected_field();

        if (null === $field) {
            return array();
        }

        $image_id = absint($this->get_pages_field_value($field));

        if (!$image_id) {
            return array();
        }

        $image_url = wp_get_attachment_image_url($image_id, 'full');

        if (!$image_url) {
            return array();
        }

        return array(
            'id'  => $image_id,
            'url' => $image_url,
        );
    }
}
