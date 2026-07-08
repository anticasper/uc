<?php
/**
 * Meta key constants for all CPTs.
 *
 * Centralizes all post meta keys used by the plugin to avoid
 * hardcoded strings scattered across files.
 *
 * @since      1.6.0
 * @package    Um_Dia_No_Parque
 * @subpackage Um_Dia_No_Parque/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta key constants.
 *
 * Usage: Um_Dia_No_Parque_Meta::UC_ENDERECO
 */
class Um_Dia_No_Parque_Meta {

    // ============================================================
    // Unidade de Conservação (UC)
    // ============================================================
    const UC_BREVE_DESCRICAO     = '_uc_breve_descricao';
    const UC_RESPONSAVEL         = '_uc_responsavel_atividade';
    const UC_EMAIL               = '_uc_email';
    const UC_WHATSAPP            = '_uc_whatsapp';
    const UC_REALIZADOR          = '_uc_realizador_atividade';
    const UC_CEP                 = '_uc_cep';
    const UC_ENDERECO            = '_uc_endereco';
    const UC_NUMERO              = '_uc_numero';
    const UC_LINK_ENDERECO       = '_uc_link_endereco';
    const UC_IMAGEM              = '_uc_imagem';
    const UC_SOCIAL              = '_uc_social';

    const UC_ATIVIDADE_IDS       = '_uc_atividade_ids';

    // ============================================================
    // Atividade
    // ============================================================
    const ATIVIDADE_DATA         = '_atividade_data';
    const ATIVIDADE_HORARIO      = '_atividade_horario';
    const ATIVIDADE_DESCRICAO    = '_atividade_descricao';
    const ATIVIDADE_ATIVO        = '_atividade_ativo';

    // ============================================================
    // Depoimento
    // ============================================================
    const DEPOIMENTO_URL_VIDEO   = '_depoimento_url_video';
    const DEPOIMENTO_UPLOAD      = '_depoimento_upload_video_foto';

    // ============================================================
    // Parceiro
    // ============================================================
    const PARCEIRO_LINK          = '_parceiro_link';

    // ============================================================
    // UF
    // ============================================================
    const UF_SIGLA               = '_uf_sigla';

    // ============================================================
    // O que levar
    // ============================================================
    const OQUE_LEVAR_ICONE       = '_oque_levar_icone';

    // ============================================================
    // Dificuldade (term meta)
    // ============================================================
    const DIFICULDADE_LEVEL       = '_dificuldade_level';
}
