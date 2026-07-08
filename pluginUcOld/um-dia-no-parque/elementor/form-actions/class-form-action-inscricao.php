<?php
/**
 * Elementor Pro Form Action: Notificação por E-mail
 *
 * Sends email notification for form submissions via Elementor Pro.
 * Simplified version — no longer saves CPT (removed per diagram).
 *
 * @since      1.0.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/elementor/form-actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Um_Dia_No_Parque_Form_Action_Inscricao extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    public function get_name() {
        return 'um_dia_no_parque_inscricao';
    }

    public function get_label() {
        return esc_html__('Notificação de Inscrição (Email)', 'um-dia-no-parque');
    }

    public function register_settings_section($widget) {
        $widget->start_controls_section(
            'section_umdnp_inscricao',
            array(
                'label'     => esc_html__('Notificação Um Dia No Parque', 'um-dia-no-parque'),
                'condition' => array(
                    'submit_actions' => $this->get_name(),
                ),
            )
        );

        $widget->add_control(
            'umdnp_inscricao_parque_id',
            array(
                'label'       => esc_html__('Parque (ID)', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'description' => esc_html__('ID do parque para associar. Deixe 0 para usar o parque atual da página.', 'um-dia-no-parque'),
                'default'     => 0,
                'min'         => 0,
            )
        );

        $widget->add_control(
            'umdnp_inscricao_email_to',
            array(
                'label'       => esc_html__('E-mail de Notificação', 'um-dia-no-parque'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => get_option('admin_email'),
                'description' => esc_html__('Para quem enviar a notificação.', 'um-dia-no-parque'),
            )
        );

        $widget->end_controls_section();
    }

    public function run($record, $ajax_handler) {
        $settings = $record->get('form_settings');
        $raw_fields = $record->get('fields');

        $fields = array();
        foreach ($raw_fields as $key => $field) {
            $fields[$key] = sanitize_text_field(wp_unslash($field['value'] ?? ''));
        }

        $parque_id = !empty($settings['umdnp_inscricao_parque_id'])
            ? absint($settings['umdnp_inscricao_parque_id'])
            : (is_singular('uc') ? get_the_ID() : 0);

        $to = !empty($settings['umdnp_inscricao_email_to'])
            ? sanitize_email($settings['umdnp_inscricao_email_to'])
            : get_option('admin_email');

        $this->send_notification($fields, $parque_id, $to);
    }

    private function send_notification($fields, $parque_id, $to) {
        $nome     = !empty($fields['nome'])     ? $fields['nome']     : __('Não informado', 'um-dia-no-parque');
        $email    = !empty($fields['email'])    ? $fields['email']    : '';
        $telefone = !empty($fields['telefone']) ? $fields['telefone'] : __('Não informado', 'um-dia-no-parque');
        $mensagem = !empty($fields['mensagem']) ? $fields['mensagem'] : '';

        $parque_nome = $parque_id ? get_the_title($parque_id) : __('Não especificado', 'um-dia-no-parque');

        $subject = sprintf(__('[Um Dia No Parque] Nova mensagem de %s', 'um-dia-no-parque'), $nome);

        $body = array();
        $body[] = __('Nova mensagem recebida através do formulário.', 'um-dia-no-parque');
        $body[] = '';
        $body[] = __('Parque:', 'um-dia-no-parque') . ' ' . $parque_nome;
        $body[] = '';
        $body[] = __('Dados do Contato:', 'um-dia-no-parque');
        $body[] = __('Nome:', 'um-dia-no-parque') . ' ' . $nome;
        $body[] = __('E-mail:', 'um-dia-no-parque') . ' ' . $email;
        $body[] = __('Telefone:', 'um-dia-no-parque') . ' ' . $telefone;

        if (!empty($mensagem)) {
            $body[] = '';
            $body[] = __('Mensagem:', 'um-dia-no-parque');
            $body[] = $mensagem;
        }

        $body[] = '';
        $body[] = '---';
        $body[] = __('Este e-mail foi enviado automaticamente pelo plugin Um Dia No Parque.', 'um-dia-no-parque');

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
        );

        if (!empty($email)) {
            $headers[] = 'Reply-To: ' . $nome . ' <' . $email . '>';
        }

        wp_mail($to, $subject, implode("\n", $body), $headers);
    }

    public function on_export($element) {
        unset(
            $element['settings']['umdnp_inscricao_parque_id'],
            $element['settings']['umdnp_inscricao_email_to'],
            $element['settings']['umdnp_inscricao_email_subject'],
            $element['settings']['umdnp_inscricao_email_body']
        );
        return $element;
    }
}
