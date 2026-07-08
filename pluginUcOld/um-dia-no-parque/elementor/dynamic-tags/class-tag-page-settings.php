<?php
/**
 * Elementor Pro Dynamic Tag: Conteúdo das Páginas
 *
 * Tag única com seleção de grupo (Home, Atividades, Experiências, O Movimento)
 * e campo dentro do grupo. Exibe os conteúdos configurados nas abas de
 * Páginas do admin do plugin (TEXT_CATEGORY).
 *
 * @since      1.8.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/dynamic-tags
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Page Settings dynamic tag — Elementor 4.x compatible.
 *
 * Tag com controle de grupo + campo para exibir conteúdos das páginas
 * configurados no admin (Home, Atividades, Experiências, O Movimento).
 *
 * @since 1.8.0
 */
class Um_Dia_No_Parque_Tag_Page_Settings extends \ElementorPro\Modules\DynamicTags\Tags\Base\Tag {

    use Um_Dia_No_Parque_Tag_Pages_Base;

    /**
     * Tag name.
     *
     * @since  1.8.0
     * @return string
     */
    public function get_name() {
        return 'page-settings';
    }

    /**
     * Tag title.
     *
     * @since  1.8.0
     * @return string
     */
    public function get_title() {
        return esc_html__('Conteúdo das Páginas', 'um-dia-no-parque');
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
     * Tag categories — text category for use in text/HTML fields.
     *
     * @since  1.8.0
     * @return array
     */
    public function get_categories() {
        return array(\ElementorPro\Modules\DynamicTags\Module::TEXT_CATEGORY);
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
                'label'   => esc_html__('Campo (Home)', 'um-dia-no-parque'),
                'options' => $this->get_home_options(),
                'default' => 'home_banner_slide_title',
            ),
            'atividades' => array(
                'label'   => esc_html__('Campo (Atividades)', 'um-dia-no-parque'),
                'options' => $this->get_tab_options('atividades'),
                'default' => 'atividades_title',
            ),
            'experiencias' => array(
                'label'   => esc_html__('Campo (Experiências)', 'um-dia-no-parque'),
                'options' => $this->get_tab_options('experiencias'),
                'default' => 'experiencias_title',
            ),
            'movimento' => array(
                'label'   => esc_html__('Campo (O Movimento)', 'um-dia-no-parque'),
                'options' => $this->get_tab_options('movimento'),
                'default' => 'movimento_title',
            ),
        ));
    }

    /**
     * Get available field options for the Home tab.
     *
     * Organizado por subseções com prefixo descritivo para facilitar
     * a identificação no editor Elementor.
     *
     * @since  1.8.0
     * @return array
     */
    public function get_home_options() {
        return array(
            // Banner Slide
            'home_banner_slide_title'    => esc_html__('[Banner] Título', 'um-dia-no-parque'),
            'home_banner_slide_subtitle' => esc_html__('[Banner] Subtítulo', 'um-dia-no-parque'),
            'home_banner_slide_btn1_text' => esc_html__('[Banner] Botão 1 — Texto', 'um-dia-no-parque'),
            'home_banner_slide_btn1_url'  => esc_html__('[Banner] Botão 1 — URL', 'um-dia-no-parque'),
            'home_banner_slide_btn2_text' => esc_html__('[Banner] Botão 2 — Texto', 'um-dia-no-parque'),
            'home_banner_slide_btn2_url'  => esc_html__('[Banner] Botão 2 — URL', 'um-dia-no-parque'),

            // O que é
            'home_o_que_e_title'    => esc_html__('[O que é] Título', 'um-dia-no-parque'),
            'home_o_que_e_subtitle' => esc_html__('[O que é] Subtítulo', 'um-dia-no-parque'),
            'home_o_que_e_text'     => esc_html__('[O que é] Texto', 'um-dia-no-parque'),
            'home_o_que_e_btn_text' => esc_html__('[O que é] Botão — Texto', 'um-dia-no-parque'),
            'home_o_que_e_btn_url'  => esc_html__('[O que é] Botão — URL', 'um-dia-no-parque'),

            // O Maior Movimento
            'home_maior_movimento_title'    => esc_html__('[Movimento] Título', 'um-dia-no-parque'),
            'home_maior_movimento_subtitle' => esc_html__('[Movimento] Subtítulo', 'um-dia-no-parque'),
            'home_maior_movimento_text'     => esc_html__('[Movimento] Texto', 'um-dia-no-parque'),
            'home_maior_movimento_btn_text' => esc_html__('[Movimento] Botão — Texto', 'um-dia-no-parque'),
            'home_maior_movimento_btn_url'  => esc_html__('[Movimento] Botão — URL', 'um-dia-no-parque'),

            // Experiência
            'home_experiencia_title'       => esc_html__('[Experiência] Título', 'um-dia-no-parque'),
            'home_experiencia_description' => esc_html__('[Experiência] Descrição', 'um-dia-no-parque'),

            // CTA
            'home_cta_title'       => esc_html__('[CTA] Título', 'um-dia-no-parque'),
            'home_cta_description' => esc_html__('[CTA] Descrição', 'um-dia-no-parque'),
            'home_cta_btn_text'    => esc_html__('[CTA] Botão — Texto', 'um-dia-no-parque'),
            'home_cta_btn_url'     => esc_html__('[CTA] Botão — URL', 'um-dia-no-parque'),

            // Como Participar
            'home_como_participar_title'      => esc_html__('[Como Participar] Título', 'um-dia-no-parque'),
            'home_como_participar_subtitle'   => esc_html__('[Como Participar] Subtítulo', 'um-dia-no-parque'),
            'home_como_participar_descricao'  => esc_html__('[Como Participar] Descrição', 'um-dia-no-parque'),
            'home_como_participar_card1_titulo'  => esc_html__('[Como Participar] Card 1 — Título', 'um-dia-no-parque'),
            'home_como_participar_card1_subtitulo' => esc_html__('[Como Participar] Card 1 — Subtítulo', 'um-dia-no-parque'),
            'home_como_participar_card2_titulo'  => esc_html__('[Como Participar] Card 2 — Título', 'um-dia-no-parque'),
            'home_como_participar_card2_subtitulo' => esc_html__('[Como Participar] Card 2 — Subtítulo', 'um-dia-no-parque'),
            'home_como_participar_card3_titulo'  => esc_html__('[Como Participar] Card 3 — Título', 'um-dia-no-parque'),
            'home_como_participar_card3_subtitulo' => esc_html__('[Como Participar] Card 3 — Subtítulo', 'um-dia-no-parque'),
            'home_como_participar_card4_titulo'  => esc_html__('[Como Participar] Card 4 — Título', 'um-dia-no-parque'),
            'home_como_participar_card4_subtitulo' => esc_html__('[Como Participar] Card 4 — Subtítulo', 'um-dia-no-parque'),

            // Compartilhe
            'home_compartilhe_title'    => esc_html__('[Compartilhe] Título', 'um-dia-no-parque'),
            'home_compartilhe_subtitle' => esc_html__('[Compartilhe] Subtítulo', 'um-dia-no-parque'),
            'home_compartilhe_descricao' => esc_html__('[Compartilhe] Descrição', 'um-dia-no-parque'),
            'home_compartilhe_videos'   => esc_html__('[Compartilhe] Vídeos (URLs)', 'um-dia-no-parque'),
        );
    }

    /**
     * Get available field options for a simple tab (Atividades, Experiências, O Movimento).
     *
     * @since  1.8.0
     * @param  string $prefix Tab prefix (atividades, experiencias, movimento).
     * @return array
     */
    protected function get_tab_options($prefix) {
        $labels = array(
            'title'       => esc_html__('Título', 'um-dia-no-parque'),
            'subtitle'    => esc_html__('Subtítulo', 'um-dia-no-parque'),
            'description' => esc_html__('Descrição', 'um-dia-no-parque'),
            'cta_text'    => esc_html__('Botão (CTA) — Texto', 'um-dia-no-parque'),
            'cta_url'     => esc_html__('Botão (CTA) — URL', 'um-dia-no-parque'),
        );

        $options = array();
        foreach ($labels as $key => $label) {
            $options["{$prefix}_{$key}"] = $label;
        }

        return $options;
    }

    /**
     * Render the tag value.
     *
     * Obtém o campo selecionado da option `um_dia_no_parque_pages`.
     *
     * @since 1.8.0
     */
    public function render() {
        $field = $this->get_selected_field();

        if (null === $field) {
            return;
        }

        $value = $this->get_pages_field_value($field);

        if (null === $value) {
            return;
        }

        // Default: output as-is with KSES for HTML fields (description/editor).
        echo wp_kses_post($value);
    }
}
